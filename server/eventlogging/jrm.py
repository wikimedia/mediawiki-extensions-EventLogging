# -*- coding: utf-8 -*-
"""
  eventlogging.jrm
  ~~~~~~~~~~~~~~~~

  This module provides a simple object-relational mapper for JSON
  schemas and the objects they describe (hence 'jrm').

"""
from __future__ import division

import argparse
import logging
import sys

import sqlalchemy

from .schema import CAPSULE_SCID, get_schema
from .compat import items
from .parse import epoch_to_datetime


__all__ = ('store_event', 'flatten', 'schema_mapper')

table_name_format = '%s_%s'

#: Mapping of JSON schema types to SQL types
sql_types = {
    'boolean': sqlalchemy.types.Boolean,
    'integer': sqlalchemy.types.Integer,
    'number': sqlalchemy.types.Float,
    'string': sqlalchemy.types.String(255),
}


def generate_column(name, descriptor):
    """Creates a column from a JSON Schema property specifier."""
    if 'timestamp' in name:
        sql_type = sqlalchemy.types.DateTime
    else:
        sql_type = sql_types.get(descriptor['type'], sql_types['string'])
    nullable = not descriptor.get('required', False)
    return sqlalchemy.Column(sql_type, nullable=nullable)


def get_or_create_table(meta, scid):
    """Loads or creates a table for a SCID."""
    try:
        return sqlalchemy.Table(table_name_format % scid, meta, autoload=True)
    except sqlalchemy.exc.NoSuchTableError:
        return create_table(meta, scid)


def create_table(meta, scid):
    """Creates a table for a SCID."""
    schema = get_schema(scid, encapsulate=True)

    # Every table gets an int auto-increment primary key:
    columns = [sqlalchemy.Column('id', types.Integer, primary_key=True)]
    columns.extend(schema_mapper(schema))

    table = sqlalchemy.Table(table_name_format % scid, meta, *columns)
    table.create()
    return table


def store_event(meta, event):
    """Store an event the database."""
    event = flatten(event)
    # Gross: we special-case keys with 'timestamp' in their name and
    # force their type to be datetime. TODO(ori-l, 28-Dec-2012): Use
    # JSON Schema's 'format'.
    for key in event:
        if 'timestamp' in key:
            event[key] = epoch_to_datetime(event[key])

    try:
        scid = (event['schema'], event['revision'])
        table = get_or_create_table(meta, scid)
    except Exception:
        logging.exception('Unable to get or set suitable table')
    else:
        table.insert(values=event).execute()


def _prefix(d, prefix):
    """Prepend a string to each key in an iterable of dict items."""
    return ((prefix + k, v) for k, v in d)


def _property_getter(key, val):
    """Mapper for :func:`flatten` that extracts properties and their
    types from schema."""
    if isinstance(val, dict):
        if 'properties' in val:
            return key, val['properties']
        if 'type' in val:
            return key, generate_column(key, val)
    return key, val


def flatten(d, sep='_', map=None):
    """Collapse a nested dictionary.

    :param sep: Key path fragment separator.
    :param map: Optional function to apply to each value.
    """
    items = []
    for k, v in d.iteritems():
        if map is not None:
            k, v = map(k, v)
        if isinstance(v, dict):
            items.extend(_prefix(flatten(v, sep, map).iteritems(), k + sep))
        else:
            items.append((k, v))
    return dict(items)


def schema_mapper(schema):
    """Takes a schema and map its properties to database column
    definitions."""
    map = flatten(schema.get('properties', schema), map=_property_getter)
    columns = []
    for name, column in items(map):
        column.name = name
        columns.append(column)
    return columns
