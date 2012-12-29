#!/usr/bin/env python
# -*- coding: utf-8 -*-
import json
import logging
import sys
import zmq

from datetime import datetime
from sqlalchemy import types, MetaData, Column, Table
from sqlalchemy.exc import SQLAlchemyError, NoSuchTableError
from log2json import get_schema


ZMQ_ENDPOINT = b'tcp://localhost:8484'
META_PROPERTIES_SCHEMA = 4933665

table_name_format = '{name}_{rev}'

logging.basicConfig(stream=sys.stderr, level=logging.DEBUG)
logging.getLogger('sqlalchemy.engine').setLevel(logging.INFO)

with open('config.json') as f:
    config = json.load(f)
meta = MetaData(**config.get('db'))

# Mapping of JSON schema types to SQL types:
sql_types = {
    'boolean': types.Boolean,
    'integer': types.Integer,
    'number': types.Float,
    'string': types.String(255),
}


def generate_column(name, descriptor):
    """Create a column from a JSON Schema property specifier."""
    json_type = descriptor['type']
    sql_type = sql_types.get(json_type, sql_types['string'])
    if 'timestamp' in name:
        sql_type = types.DateTime
    nullable = not descriptor.get('required', False)
    return Column(name, sql_type, nullable=nullable)


def get_table(name, rev):
    """Loads or creates a table for a given schema name and revision."""
    table_name = table_name_format.format(name=name, rev=rev)
    try:
        return Table(table_name, meta, autoload=True)
    except NoSuchTableError:
        return create_table(name, rev)


def create_table(name, rev):
    """Creates a table for a given schema name and revision."""
    schema_self = get_schema(rev)
    schema_meta = get_schema(META_PROPERTIES_SCHEMA)

    # Every table gets an int auto-increment primary key:
    columns = [Column('id', types.Integer, primary_key=True)]

    for schema in (schema_self, schema_meta):
        properties = schema['properties']
        columns.extend(generate_column(k, v) for k, v in properties.items())

    table_name = table_name_format.format(name=name, rev=rev)
    table = Table(table_name, meta, *columns)
    table.create()
    return table


def store_event(event):
    # Gross: we special-case keys with 'timestamp' in their name and
    # force their type to be datetime. TODO(ori-l, 28-Dec-2012): Use
    # JSON Schema's 'format'.
    for key in event:
        if 'timestamp' in key:
            event[key] = datetime.fromtimestamp(int(event[key]))

    try:
        table = get_table(event['_schema'], event['_revision'])
    except Exception:
        logging.exception('Unable to get or set suitable table')
    else:
        table.insert(values=event).execute()


context = zmq.Context()
socket = context.socket(zmq.SUB)
socket.connect(ZMQ_ENDPOINT)
socket.setsockopt(zmq.SUBSCRIBE, b'')

while 1:
    try:
        ev = socket.recv_json()
        logging.info(ev)
        store_event(ev)
    except SQLAlchemyError:
        logging.exception('Unable to insert event: %s', ev)
