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
import glob
import imp
import io
import json
import logging
import logging.handlers
import os
import socket
import sys

import pymongo
import sqlalchemy
import zmq

from .factory import writes, reads, mapper
from .compat import urlparse
from .jrm import store_sql_event


__all__ = ('load_plugins',)

#: EventLogging will attempt to load the configuration file specified in the
#: 'EVENTLOGGING_PLUGIN_DIR' environment variable if it is defined. If it is
#: not defined, EventLogging will default to the value specified below.
DEFAULT_PLUGIN_DIR = '/usr/local/lib/eventlogging'

UDP_BLOCK_SIZE = 65536  # Corresponds to Udp2LogConfig::BLOCK_SIZE


def iter_text(f, encoding='utf8', errors='replace', **kwargs):
    """Returns an iterator that decodes data from a file-like object opened in
    binary mode into lines of unicode text."""
    return io.open(f.fileno(), encoding=encoding, errors=errors, **kwargs)


def load_plugins(path=None):
    """Load EventLogging plug-ins from `path`. Plug-in module names are mangled
    to prevent clobbering modules in the Python module search path."""
    if path is None:
        path = os.environ.get('EVENTLOGGING_PLUGIN_DIR', DEFAULT_PLUGIN_DIR)
    for plugin in glob.glob(os.path.join(path, '*.py')):
        imp.load_source('__eventlogging_plugin_%x__' % hash(plugin), plugin)


#
# Mappers
#

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
    engine = sqlalchemy.create_engine(uri)
    meta = sqlalchemy.MetaData(bind=engine)

    while 1:
        store_sql_event(meta, (yield))


@writes('file')
def log_writer(uri):
    """Write events to a file on disk."""
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
    """Publish events on a ZeroMQ publisher socket."""
    context = zmq.Context.instance()
    pub = context.socket(zmq.PUB)
    pub.bind(uri)

    while 1:
        json_event = json.dumps((yield), check_circular=False)
        pub.send_unicode(json_event + '\n')


@writes('stdout')
def stdout_writer(uri, **kwargs):
    """Writes events to stdout. Pretty-prints if stdout is a terminal."""
    if sys.stdout.isatty():
        kwargs.setdefault('indent', 2)
    while 1:
        print(json.dumps((yield), sort_keys=True, **kwargs))


#
# Readers
#


@reads('stdin')
def stdin_reader(uri, **kwargs):
    """Reads data from standard input."""
    return iter_text(sys.stdin, **kwargs)


@reads('tcp')
def zmq_subscriber(uri, socket_id=None, topic=''):
    """Reads data from a ZeroMQ publisher."""
    if '?' in uri:
        uri = uri[:uri.index('?')]
    context = zmq.Context.instance()
    sub = context.socket(zmq.SUB)
    if socket_id is not None:
        sub.setsockopt(zmq.IDENTITY, socket_id.encode('utf8'))
    sub.connect(uri)
    sub.setsockopt(zmq.SUBSCRIBE, topic.encode('utf8'))

    while 1:
        yield json.loads(sub.recv_unicode())


@reads('udp')
def udp_reader(uri):
    """Reads data from a UDP socket."""
    ip, port = urlparse(uri).netloc.split(':')
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    sock.bind((ip, int(port)))
    return iter_text(sock, buffering=UDP_BLOCK_SIZE)
