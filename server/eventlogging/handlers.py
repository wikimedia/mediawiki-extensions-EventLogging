# -*- coding: utf-8 -*-
"""
  eventlogging.handlers
  ~~~~~~~~~~~~~~~~~~~~~

  This class contains the set of event readers and event writers that ship with
  EventLogging. Event readers are generators that yield successive events from
  a stream. Event writers are coroutines that receive events and handle them
  somehow. Both readers and writers are designed to be configurable using URIs.
  :func:`eventlogging.drive` pumps data through a reader-writer pair.

"""
import datetime
import io
import json
import logging
import logging.handlers
import socket
import sys

import pymongo
import sqlalchemy
import zmq

from .factory import writes, reads, mapper
from .compat import urlparse
from .jrm import store_sql_event


# Mappers

@mapper
def decode_json(stream):
    return (json.loads(val) for val in stream)


@mapper
def count(stream):
    return (str(id) + '\t' + val for id, val in enumerate(stream))


#
# Writers
#

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
def log_writer(uri):
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
def zmq_publisher(uri):
    context = zmq.Context.instance()
    pub = context.socket(zmq.PUB)
    pub.bind(uri)

    while 1:
        json_event = json.dumps((yield), check_circular=False)
        pub.send_unicode(json_event + '\n')


@writes('stdout')
def stdout_writer():
    kwargs = {}
    if sys.stdout.isatty():
        kwargs.update(sort_keys=True, indent=2)
    while 1:
        print(json.dumps((yield), **kwargs))


@reads('stdin')
def stdin_reader(encoding='utf8', errors='ignore'):
    return io.open(sys.stdin.fileno(), encoding=encoding, errors=errors)


# Readers

@reads('tcp')
def zmq_subscriber(uri, socket_id=None, topic=''):
    context = zmq.Context.instance()
    sub = context.socket(zmq.SUB)
    if socket_id is not None:
        sub.setsockopt(zmq.IDENTITY, socket_id.encode('utf8'))
    sub.connect(url[:url.find('?')])
    sub.setsockopt(zmq.SUBSCRIBE, topic.encode('utf8'))

    while 1:
        yield sub.recv_unicode()


UDP_BUFSIZE = 65536  # Udp2LogConfig::BLOCK_SIZE


@reads('udp')
def udp_reader(uri):
    parsed = urlparse(uri)
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    sock.bind(parsed.netloc.split(':'))
    return io.open(sock.fileno(), buffering=UDP_BUFSIZE, encoding='utf8')
