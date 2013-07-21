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
import json
import logging
import logging.handlers
import os
import sys

import pymongo
import sqlalchemy

from .factory import writes, reads, mapper
from .compat import urlparse
from .stream import (iter_socket, iter_socket_json, pub_socket,
                     sub_socket, udp_socket)
from .jrm import store_sql_event


__all__ = ('load_plugins',)

#: EventLogging will attempt to load the configuration file specified in the
#: 'EVENTLOGGING_PLUGIN_DIR' environment variable if it is defined. If it is
#: not defined, EventLogging will default to the value specified below.
DEFAULT_PLUGIN_DIR = '/usr/local/lib/eventlogging'


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
def mongodb_writer(uri):
    client = pymongo.MongoClient(uri)
    db = client[map['database'] or 'events']
    datetime_from_timestamp = datetime.datetime.fromtimestamp

    while 1:
        event = (yield)
        event['timestamp'] = datetime_from_timestamp(event['timestamp'])
        event['_id'] = event['uuid']
        collection = event['schema']
        db[collection].insert(event)


@writes('mysql', 'sqlite')
def sql_writer(uri):
    engine = sqlalchemy.create_engine(uri)
    meta = sqlalchemy.MetaData(bind=engine)

    while 1:
        store_sql_event(meta, (yield))


@writes('file')
def log_writer(uri):
    """Write events to a file on disk."""
    filename = urlparse(uri).path
    handler = logging.handlers.WatchedFileHandler(filename)
    log = logging.getLogger('Events')
    log.setLevel(logging.INFO)
    log.addHandler(handler)

    while 1:
        json_event = json.dumps((yield), check_circular=False)
        log.info(json_event)


@writes('tcp')
def zeromq_writer(uri):
    """Publish events on a ZeroMQ publisher socket."""
    pub = pub_socket(uri)
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
def stdin_reader(uri):
    """Reads data from standard input."""
    return iter_socket(sys.stdin)


@reads('tcp')
def zeromq_subscriber(uri, socket_id=None, subscribe=''):
    """Reads data from a ZeroMQ publisher."""
    sub = sub_socket(uri, identity=socket_id, subscribe=subscribe)
    for event in iter_socket_json(sub):
        yield event


@reads('udp')
def udp_reader(uri):
    """Reads data from a UDP socket."""
    return iter_socket(udp_socket(uri))
