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
import collections
import datetime
import glob
import imp
import json
import logging
import logging.handlers
import os
import socket
import sys

import sqlalchemy

from .utils import PeriodicThread, uri_delete_query_item
from .factory import writes, reads
from .streams import stream, pub_socket, sub_socket, udp_socket
from .jrm import store_sql_events, DB_FLUSH_INTERVAL


__all__ = ('load_plugins',)

# EventLogging will attempt to load the configuration file specified in the
# 'EVENTLOGGING_PLUGIN_DIR' environment variable if it is defined. If it is
# not defined, EventLogging will default to the value specified below.
DEFAULT_PLUGIN_DIR = '/usr/local/lib/eventlogging'


def load_plugins(path=None):
    """Load EventLogging plug-ins from `path`. Plug-in module names are mangled
    to prevent clobbering modules in the Python module search path."""
    if path is None:
        path = os.environ.get('EVENTLOGGING_PLUGIN_DIR', DEFAULT_PLUGIN_DIR)
    for plugin in glob.glob(os.path.join(path, '*.py')):
        imp.load_source('__eventlogging_plugin_%x__' % hash(plugin), plugin)


#
# Writers
#

@writes('mongodb')
def mongodb_writer(uri, database='events'):
    import pymongo

    client = pymongo.MongoClient(uri)
    db = client[database]
    datetime_from_timestamp = datetime.datetime.fromtimestamp

    while 1:
        event = (yield)
        event['timestamp'] = datetime_from_timestamp(event['timestamp'])
        event['_id'] = event['uuid']
        collection = event['schema']
        db[collection].insert(event)


@writes('kafka')
def kafka_writer(brokers, topic='eventlogging'):
    """Write events to Kafka, keyed by SCID."""
    from kafka.client import KafkaClient
    from kafka.producer import KeyedProducer

    kafka = KafkaClient(brokers)
    producer = KeyedProducer(kafka)
    topic = topic.encode('utf-8')

    while 1:
        event = (yield)
        key = '%(schema)s_%(revision)s' % event  # e.g. 'EchoMail_5467650'
        key = key.encode('utf-8')
        producer.send(topic, key, json.dumps(event, sort_keys=True))


@writes('mysql', 'sqlite')
def sql_writer(uri, replace=False):
    """Writes to an RDBMS, creating tables for SCIDs and rows for events."""
    # Don't pass 'replace' parameter to SQLAlchemy.
    uri = uri_delete_query_item(uri, 'replace')

    engine = sqlalchemy.create_engine(uri)
    meta = sqlalchemy.MetaData(bind=engine)
    events = collections.deque()
    worker = PeriodicThread(interval=DB_FLUSH_INTERVAL,
                            target=store_sql_events,
                            args=(meta, events, replace))
    worker.start()

    if meta.bind.dialect.name == 'mysql':
        @sqlalchemy.event.listens_for(sqlalchemy.pool.Pool, 'checkout')
        def ping(dbapi_connection, connection_record, connection_proxy):
            # Just before executing an insert, call mysql_ping() to verify
            # that the connection is alive, and reconnect if necessary.
            dbapi_connection.ping(True)

    try:
        # Link the main thread to the worker thread so we
        # don't keep filling the queue if the worker died.
        while worker.is_alive():
            event = (yield)
            events.append(event)
            if len(events) >= 100:
                worker.ready.set()
    except GeneratorExit:
        # Allow the worker to complete any work that is
        # already in progress before shutting down.
        worker.stop()
        worker.join()
    finally:
        # If there are any events remaining in the queue,
        # process them in the main thread before exiting.
        if events:
            store_sql_events(meta, events)


@writes('file')
def log_writer(path):
    """Write events to a file on disk."""
    handler = logging.handlers.WatchedFileHandler(path)
    log = logging.getLogger('Events')
    log.setLevel(logging.INFO)
    log.addHandler(handler)

    while 1:
        event = (yield)
        json_event = json.dumps(event, sort_keys=True, check_circular=False)
        log.info(json_event)


@writes('tcp')
def zeromq_writer(uri):
    """Publish events on a ZeroMQ publisher socket."""
    pub = pub_socket(uri)
    while 1:
        event = (yield)
        json_event = json.dumps(event, sort_keys=True, check_circular=False)
        pub.send_unicode(json_event + '\n')


@writes('statsd')
def statsd_writer(hostname, port, prefix='eventlogging.schema'):
    """Increments StatsD SCID counters for each event."""
    addr = socket.gethostbyname(hostname), port
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    while 1:
        event = (yield)
        stat = prefix + '.%(schema)s:1|m' % event
        sock.sendto(stat.encode('utf-8'), addr)


@writes('stdout')
def stdout_writer(uri):
    """Writes events to stdout. Pretty-prints if stdout is a terminal."""
    dumps_kwargs = dict(sort_keys=True, check_circular=False)
    if sys.stdout.isatty():
        dumps_kwargs.update(indent=2)
    while 1:
        event = (yield)
        print(json.dumps(event, **dumps_kwargs))


#
# Readers
#

@reads('stdin')
def stdin_reader(uri, raw=False):
    """Reads data from standard input."""
    return stream(sys.stdin, raw)


@reads('tcp')
def zeromq_subscriber(uri, socket_id=None, subscribe='', raw=False):
    """Reads data from a ZeroMQ publisher. If `raw` is truthy, reads
    unicode strings. Otherwise, reads JSON."""
    sock = sub_socket(uri, identity=socket_id, subscribe=subscribe)
    return stream(sock, raw)


@reads('udp')
def udp_reader(uri, raw=False):
    """Reads data from a UDP socket."""
    return stream(udp_socket(uri), raw)
