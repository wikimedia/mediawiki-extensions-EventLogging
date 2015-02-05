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
import itertools

import sqlalchemy

from .compat import items
from .schema import get_schema
from .utils import flatten


__all__ = ('store_sql_events',)


# Format string for :func:`datetime.datetime.strptime` for MediaWiki
# timestamps. See `<http://www.mediawiki.org/wiki/Manual:Timestamp>`_.
MEDIAWIKI_TIMESTAMP = '%Y%m%d%H%M%S'

# Format string for table names. Interpolates a `SCID` -- i.e., a tuple
# of (schema_name, revision_id).
TABLE_NAME_FORMAT = '%s_%s'

# An iterable of properties that should not be stored in the database.
NO_DB_PROPERTIES = ('recvFrom', 'revision', 'schema', 'seqId')

# A dictionary mapping database engine names to table defaults.
ENGINE_TABLE_OPTIONS = {
    'mysql': {
        'mysql_charset': 'utf8',
        'mysql_engine': 'InnoDB'
    }
}

# How long (in seconds) we should accumulate events before flushing
# to the database.
DB_FLUSH_INTERVAL = 2


class MediaWikiTimestamp(sqlalchemy.TypeDecorator):
    """A :class:`sqlalchemy.TypeDecorator` for MediaWiki timestamps."""

    # Timestamps are stored as VARCHAR(14) columns.
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


# Maximum length for string and string-like types. Because InnoDB limits index
# columns to 767 bytes, the maximum length for a utf8mb4 column (which
# reserves up to four bytes per character) is 191 (191 * 4 = 764).
STRING_MAX_LEN = 191

# Default table column definition, to be overridden by mappers below.
COLUMN_DEFAULTS = {'type_': sqlalchemy.Unicode(STRING_MAX_LEN)}

# Mapping of JSON Schema attributes to valid values. Each value maps to
# a dictionary of options. The options are compounded into a single
# dict, which is then used as kwargs for :class:`sqlalchemy.Column`.
#
# ..note::
#
#   The mapping is keyed in order of increasing specificity. Thus a
#   JSON property {"type": "number", "format": "utc-millisec"} will
#   map onto a :class:`MediaWikiTimestamp` type, and not
#   :class:`sqlalchemy.Float`.
mappers = collections.OrderedDict((
    ('type', {
        'boolean': {'type_': sqlalchemy.Boolean},
        'integer': {'type_': sqlalchemy.BigInteger},
        'number': {'type_': sqlalchemy.Float},
        'string': {'type_': sqlalchemy.Unicode(STRING_MAX_LEN)},
    }),
    ('format', {
        'utc-millisec': {'type_': MediaWikiTimestamp, 'index': True},
        'uuid5-hex': {'type_': sqlalchemy.CHAR(32), 'index': True,
                      'unique': True},
    }),
    ('required', {
        True: {'nullable': False},
        False: {'nullable': True}
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

    columns = schema_mapper(schema)

    table_options = ENGINE_TABLE_OPTIONS.get(meta.bind.name, {})
    table_name = TABLE_NAME_FORMAT % scid

    table = sqlalchemy.Table(table_name, meta, *columns, **table_options)
    table.create(checkfirst=True)

    return table


def _insert_sequential(table, events, replace=False):
    """Insert events into the database by issuing an INSERT for each one."""
    for event in events:
        insert = table.insert(values=event)
        try:
            insert.execute()
        except sqlalchemy.exc.IntegrityError as e:
            if not replace or 'unique' not in str(e).lower():
                raise
        except sqlalchemy.exc.ProgrammingError:
            table.create()
            insert.execute()


def _insert_multi(table, events, replace=False):
    """Insert events into the database using a single INSERT."""
    insert = table.insert(values=events)
    if replace:
        insert = (insert
                  .prefix_with('IGNORE', dialect='mysql')
                  .prefix_with('OR REPLACE', dialect='sqlite'))
    try:
        insert.execute()
    except sqlalchemy.exc.SQLAlchemyError:
        table.create(checkfirst=True)
        insert.execute()


def insert_sort_key(event):
    """Sort / group function that batches events that share the same
    SCID and set of fields. We need to group together events that
    belong to the same schema AND that have the same set of fields.
    Please note that schemas allow for optional fields that might/might
    not have been included in the event.
    """
    return (event['schema'], event['revision']), tuple(sorted(event))


def store_sql_events(meta, events, replace=False):
    """Store events in the database."""
    queue = [flatten(events.pop()) for _ in range(len(events))]
    queue.sort(key=insert_sort_key)

    if (getattr(meta.bind.dialect, 'supports_multivalues_insert', False)
            or getattr(meta.bind.dialect, 'supports_multirow_insert', False)):
        insert = _insert_multi
    else:
        insert = _insert_sequential

    for (scid, _), events in itertools.groupby(queue, insert_sort_key):
        prepared_events = [prepare(event) for event in events]
        table = get_table(meta, scid)
        insert(table, prepared_events, replace)


def _property_getter(item):
    """Mapper function for :func:`flatten` that extracts properties
    and their types from schema."""
    key, val = item
    if isinstance(val, dict):
        if 'properties' in val:
            val = val['properties']
        elif 'type' in val:
            val = typecast(val)
    return key, val


def prepare(event):
    """Prepare an event for insertion into the database."""
    for prop in NO_DB_PROPERTIES:
        event.pop(prop, None)
    return event


def column_sort_key(column):
    """Sort key for column names. 'id' and 'uuid' come first, then the
    top-level properties in alphabetical order, followed by the nested
    properties (identifiable by the presence of an underscore)."""
    return (
        ('id', 'uuid', column.name).index(column.name),
        column.name.count('_'),
        column.name,
    )


def schema_mapper(schema):
    """Takes a schema and map its properties to database column
    definitions."""
    properties = {k: v for k, v in items(schema.get('properties', {}))
                  if k not in NO_DB_PROPERTIES}

    columns = []

    for name, col in items(flatten(properties, f=_property_getter)):
        col.name = name
        columns.append(col)

    columns.sort(key=column_sort_key)
    return columns
