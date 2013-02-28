# -*- coding: utf-8 -*-
"""
  eventlogging.jrm
  ~~~~~~~~~~~~~~~~

  This module provides a simple object-relational mapper for JSON
  schemas and the objects they describe (hence 'jrm').

"""
from __future__ import division, unicode_literals

import collections
import datetime

import sqlalchemy

from .schema import get_schema
from .compat import items


__all__ = ('store_event',)

#: Format string for :func:`datetime.datetime.strptime` for MediaWiki
#: timestamps. See `<http://www.mediawiki.org/wiki/Manual:Timestamp>`_.
MEDIAWIKI_TIMESTAMP = '%Y%m%d%H%M%S'

#: Format string for table names. Interpolates a `SCID` -- i.e., a tuple
#: of (schema_name, revision_id).
TABLE_NAME_FORMAT = '%s_%s'

#: An iterable of properties that should not be stored in the database.
NO_DB_PROPERTIES = ('recvFrom', 'revision', 'schema', 'seqId')

#: A dictionary mapping database engine names to table defaults.
ENGINE_TABLE_OPTIONS = {
    'mysql': {
        'mysql_charset': 'utf8',
        'mysql_engine': 'InnoDB'
    }
}


class MediaWikiTimestamp(sqlalchemy.TypeDecorator):
    """A :class:`sqlalchemy.TypeDecorator` for MediaWiki timestamps."""

    #: Timestamps are stored as VARCHAR(14) columns.
    impl = sqlalchemy.Unicode(14)

    def process_bind_param(self, value, dialect=None):
        """Convert an integer timestamp (specifying number of seconds or
        miliseconds since UNIX epoch) to MediaWiki timestamp format."""
        if value > 1e12:
            value /= 1000
        value = datetime.datetime.utcfromtimestamp(value).strftime(
            MEDIAWIKI_TIMESTAMP)
        if hasattr(value, 'decode'):
            value = value.decode('utf-8')
        return value

    def process_result_value(self, value, dialect=None):
        """Convert a MediaWiki timestamp to a :class:`datetime.datetime`
        object."""
        return datetime.datetime.strptime(value, MEDIAWIKI_TIMESTAMP)


#: Default table column definition, to be overridden by mappers below.
COLUMN_DEFAULTS = {'type_': sqlalchemy.Unicode(255)}

#: Mapping of JSON Schema attributes to valid values. Each value maps to
#: a dictionary of options. The options are compounded into a single
#: dict, which is then used as kwargs for :class:`sqlalchemy.Column`.
#:
#: ..note::
#:
#:   The mapping is keyed in order of increasing specificity. Thus a
#:   JSON property {"type": "number", "format": "utc-millisec"} will
#:   map onto a :class:`MediaWikiTimestamp` type, and not
#:   :class:`sqlalchemy.Float`.
mappers = collections.OrderedDict((
    ('type', {
        'boolean': {'type_': sqlalchemy.Boolean},
        'integer': {'type_': sqlalchemy.Integer},
        'number': {'type_': sqlalchemy.Float},
        'string': {'type_': sqlalchemy.Unicode(255)},
    }),
    ('format', {
        'utc-millisec': {'type_': MediaWikiTimestamp, 'index': True},
    }),
    ('required', {
        True: {'nullable': True},
        False: {'nullable': False}
    })
))


def typecast(property):
    """Generates a SQL column definition from a JSON Schema property
    specifier."""
    options = COLUMN_DEFAULTS.copy()
    for attribute, mapping in items(mappers):
        value = property.get(attribute)
        options.update(mapping.get(value, ()))
    return sqlalchemy.Column(**options)


def get_table(meta, scid):
    """Acquire a :class:`sqlalchemy.schema.Table` object for a JSON
    Schema specified by `scid`."""
    #  +---------------------------------+
    #  | Is description of table present |
    #  | in Python's MetaData object?    |
    #  +----+----------------------+-----+
    #       |                      |
    #       no                     yes
    #       |                      |      +---------------------+
    #       |                      +----->| Assume table exists |
    #       v                             | in DB               |
    #  +--------------------------+       +-----------+---------+
    #  | Describe table structure |                   |
    #  | using schema.            |                   |
    #  +------------+-------------+                   |
    #               |                                 |
    #               v                                 |
    #  +---------------------------+                  |
    #  | Does a table so described |                  |
    #  | exist in the database?    |                  |
    #  +----+-----------------+----+                  |
    #       |                 |                       |
    #       no                yes                     |
    #       |                 |                       |
    #       v                 |                       |
    #   +--------------+      |                       |
    #   | CREATE TABLE |      |                       |
    #   +---+----------+      |                       v
    #       |                 |         +-------------+------------+
    #       +-----------------+-------->| Return table description |
    #                                   +--------------------------+
    try:
        return meta.tables[TABLE_NAME_FORMAT % scid]
    except KeyError:
        return declare_table(meta, scid)


def declare_table(meta, scid):
    """Map a JSON schema to a SQL table. If the table does not exist in
    the database, issue ``CREATE TABLE`` statement."""
    schema = get_schema(scid, encapsulate=True)

    # Every table gets an integer auto-increment primary key column `id`
    # and an indexed CHAR(32) column, `uuid`. (UUIDs could be stored as
    # binary in a CHAR(16) column, but at the cost of readability.)
    columns = [
        sqlalchemy.Column('id', sqlalchemy.Integer, primary_key=True),
        # To keep INSERTs fast, the index on `uuid` is not unique.
        sqlalchemy.Column('uuid', sqlalchemy.CHAR(32), index=True)
    ]
    columns.extend(schema_mapper(schema))

    table_options = ENGINE_TABLE_OPTIONS.get(meta.bind.name, {})
    table_name = TABLE_NAME_FORMAT % scid

    table = sqlalchemy.Table(table_name, meta, *columns, **table_options)
    table.create(checkfirst=True)

    return table


def store_event(meta, event):
    """Store an event in the database."""
    scid = (event['schema'], event['revision'])
    table = get_table(meta, scid)
    event = flatten(event)
    event = {k: v for k, v in items(event) if k not in NO_DB_PROPERTIES}
    return table.insert(values=event).execute()


def _property_getter(item):
    """Mapper function for :func:`flatten` that extracts properties
    and their types from schema."""
    (key, val) = item
    if isinstance(val, dict):
        if 'properties' in val:
            val = val['properties']
        elif 'type' in val:
            val = typecast(val)
    return (key, val)


def flatten(d, sep='_', f=None):
    """Collapse a nested dictionary. `f` specifies an optional mapping
    function to apply to each (key, value) pair."""
    flat = []
    for k, v in items(d):
        if f is not None:
            (k, v) = f((k, v))
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

    # Sort the mapped columns lexicographically by name, with 'nested'
    # columns (identifiable by the presence of an underscore in the
    # name) appearing last.
    columns.sort(key=lambda col: ('_' in col.name, col.name))
    return columns
