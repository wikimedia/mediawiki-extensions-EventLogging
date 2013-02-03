# -*- coding: utf-8 -*-
"""
  eventlogging.jrm
  ~~~~~~~~~~~~~~~~

  This module provides a simple object-relational mapper for JSON
  schemas and the objects they describe (hence 'jrm').

"""
from __future__ import division, unicode_literals

import datetime
import logging

import sqlalchemy

from .schema import get_schema, capsule_uuid
from .compat import items


__all__ = ('store_event', 'flatten', 'schema_mapper', 'create_table')

#: Format string for :func:`datetime.datetime.strptime` for MediaWiki
#: timestamps. See `<http://www.mediawiki.org/wiki/Manual:Timestamp>`_.
MEDIAWIKI_TIMESTAMP = '%Y%m%d%H%M%S'

#: Format string for table names. Interpolates a `SCID` -- i.e., a tuple
#: of (schema_name, revision_id).
TABLE_NAME_FORMAT = '%s_%s'

#: An iterable of properties that should not be stored in the database.
NO_DB_PROPERTIES = ('schema', 'revision', 'recvFrom', 'seqId')

#: A dictionary mapping database engine names to table defaults.
TABLE_OPTIONS = {
    'mysql': {
        'mysql_charset': 'utf8',
        'mysql_engine': 'InnoDB'
    }
}


class MediaWikiTimestamp(sqlalchemy.TypeDecorator):
    """A :class:`sqlalchemy.TypeDecorator` for MediaWiki timestamps."""

    #: Timestamps are stored as VARBINARY(14) columns.
    impl = sqlalchemy.Unicode(14)

    def process_bind_param(self, value, dialect=None):
        """Bind a timestamp.
        :param value: May be either number of seconds or miliseconds
        since UNIX epoch, or a datetime object.
        """
        if not isinstance(value, datetime.datetime):
            if value > 1e12:
                value /= 1000
            value = datetime.datetime.fromtimestamp(value)
        return value.strftime(MEDIAWIKI_TIMESTAMP)

    def process_result_value(self, value, dialect=None):
        return datetime.datetime.strptime(value, MEDIAWIKI_TIMESTAMP)


#: Mapping of JSON schema types to SQL types
sql_types = {
    'boolean': sqlalchemy.Boolean,
    'integer': sqlalchemy.Integer,
    'number': sqlalchemy.Float,
    'string': sqlalchemy.Unicode(255),
}


def generate_column(name, descriptor):
    """Creates a column from a JSON Schema property specifier."""
    column_options = {}

    if 'timestamp' in name:
        # TODO(ori-l, 30-Jan-2013): Handle this in a less ad-hoc fashion,
        # ideally using the `format` specifier in JSON Schema.
        sql_type = MediaWikiTimestamp
        column_options['index'] = True  # Index timestamps.
    else:
        sql_type = sql_types.get(descriptor['type'], sql_types['string'])

    # If the column is marked 'required', make it non-nullable.
    if descriptor.get('required', False):
        column_options['nullable'] = False

    return sqlalchemy.Column(sql_type, **column_options)


def get_or_create_table(meta, scid):
    """Loads or creates a table for a SCID."""
    try:
        return sqlalchemy.Table(TABLE_NAME_FORMAT % scid, meta, autoload=True)
    except sqlalchemy.exc.NoSuchTableError:
        return create_table(meta, scid)


def create_table(meta, scid):
    """Creates a table for a SCID."""
    schema = get_schema(scid, encapsulate=True)

    # Get any table creation kwargs specific to this engine.
    opts = TABLE_OPTIONS.get(meta.bind.name, {})

    # Every table gets an integer auto-increment primary key column
    # ``id`` and a char(32) column ``uuid`` that is indexed. ``uuid``
    # could be stored as binary char(16) but we optimize for
    # readability. Although event UUIDs are presumed to be unique, we
    # don't make the index unique, because that would kill write
    # performance.
    columns = [
        sqlalchemy.Column('id', sqlalchemy.Integer, primary_key=True),
        sqlalchemy.Column('uuid', sqlalchemy.CHAR(32), index=True)
    ]
    columns.extend(schema_mapper(schema))

    table = sqlalchemy.Table(TABLE_NAME_FORMAT % scid, meta, *columns, **opts)
    table.create()
    return table


def store_event(meta, event):
    """Store an event the database."""
    try:
        scid = (event['schema'], event['revision'])
        table = get_or_create_table(meta, scid)
    except Exception:
        logging.exception('Unable to get or set suitable table')
    else:
        event = flatten(event)
        event['uuid'] = capsule_uuid(event).hex
        event = {k: v for k, v in items(event) if k not in NO_DB_PROPERTIES}
        table.insert(values=event).execute()


def _property_getter(key, val):
    """Mapper function for :func:`flatten` that extracts properties
    and their types from schema."""
    if isinstance(val, dict):
        if 'properties' in val:
            return key, val['properties']
        if 'type' in val:
            return key, generate_column(key, val)
    return key, val


def flatten(d, sep='_', f=None):
    """Collapse a nested dictionary.

    :param sep: Key path fragment separator.
    :param f: Optional function to apply to each item.
    """
    flat = []
    for k, v in items(d):
        if f is not None:
            k, v = f(k, v)
        if isinstance(v, dict):
            nested = items(flatten(v, sep, f))
            flat.extend((k + sep + nk, nv) for nk, nv in nested)
        else:
            flat.append((k, v))
    return dict(flat)


def schema_mapper(schema):
    """Takes a schema and map its properties to database column
    definitions."""
    properties = {k: v for k, v in items(schema.get('properties', {}))
                  if k not in NO_DB_PROPERTIES}
    columns = []
    for name, col in items(flatten(properties, f=_property_getter)):
        col.name = name
        columns.append(col)
    return columns
