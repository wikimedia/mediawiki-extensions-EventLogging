#!/usr/bin/env python
# -*- coding: utf-8 -*-
import datetime
import json
import logging
import logging.handlers

# import pymongo
import sqlalchemy
import zmq

from .jrm import store_sql_event
from .base import writes, reads
from .util import strip_qs
from .compat import urlparse


@writes('mongodb')
def mongodb_writer(uri, **kwargs):
    map = pymongo.uri_parser.parse_uri(uri, **kwargs)
    client = pymongo.MongoClient(uri, **kwargs)
    db = client[map['database'] or 'events']
    datetime_from_timestamp = datetime.datetime.fromtimestamp

    while 1:
        event = (yield)
        event['timestamp'] = datetime_from_timestamp(event['timestamp'])
        event['_id'] = event['uuid']
        collection = event['schema']
        db[collection].insert(event)


@writes('mysql', 'sqlite')
def sql_writer(uri, **kwargs):
    engine = sqlalchemy.create_engine(uri, **kwargs)
    meta = sqlalchemy.MetaData(bind=engine)

    while 1:
        store_sql_event(meta, (yield))


@writes('file')
def log_writer(uri, **kwargs):
    parsed = urlparse(uri)
    filename = parsed.path
    handler = logging.handlers.WatchedFileHandler(filename)
    log = logging.getLogger('Events')
    log.setLevel(logging.INFO)
    log.addHandler(handler)

    while 1:
        json_event = json.dumps((yield), check_circular=False)
        log.info(json_event)


@writes('tcp')
def zmq_publisher(uri, **kwargs):
    context = zmq.Context.instance()
    pub = context.socket(zmq.PUB)
    pub.bind(uri)

    while 1:
        json_event = json.dumps((yield), check_circular=False)
        pub.send_unicode(json_event + '\n')


@writes('stdout')
def stdout_writer(uri, **kwargs):
    while 1:
        event = (yield)
        print(json.dumps(event, sort_keys=True, indent=4))


@reads('tcp')
def zmq_subscriber(uri, socket_id=None, topic='', **kwargs):
    context = zmq.Context.instance()
    sub = context.socket(zmq.SUB)
    if socket_id is not None:
        sub.setsockopt(zmq.IDENTITY, socket_id.encode('utf8'))
    sub.connect(strip_qs(uri))
    sub.setsockopt(zmq.SUBSCRIBE, topic.encode('utf8'))

    while 1:
        yield sub.recv_json()
