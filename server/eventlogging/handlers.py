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
import time
import traceback

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
def kafka_writer(
    path,
    topic='eventlogging_%(schema)s',
    key='%(schema)s_%(revision)s',
    raw=False,
):
    """
    Write events to Kafka.

        path    - URI path should be comma separated Kafka Brokers.
                  e.g. kafka01:9092,kafka02:9092,kafka03:9092

        topic   - Python format string topic name.
                  If the incoming event is a dict (not a raw string)
                  topic will be interpolated against event.  I.e.
                  topic % event.  Default: eventlogging_%(schema)s

        key     - Python format string key of the event message in Kafka.
                  If the incoming event is a dict (not a raw string)
                  key will be interpolated against event.  I.e.
                  key % event.  Default: %(schema)s_%(revision)s

        raw     - Should the events be written as raw (encoded) or not?
    """

    # Brokers should be in the uri path
    brokers = path.strip('/')

    from kafka import KafkaClient
    from kafka import KeyedProducer
    from kafka.common import KafkaTimeoutError

    kafka = KafkaClient(brokers)
    producer = KeyedProducer(kafka)

    # These will be used if incoming events are not interpolatable.
    default_topic = topic.encode('utf8')
    default_key = key.encode('utf8')

    kafka_topic_create_timeout_seconds = 0.1

    while 1:
        event = (yield)

        # If event is a dict (not Raw) then we can interpolate topic and key
        # as format strings.
        # E.g. message_topic = 'eventlogging_%(schema)s' % event.
        # WARNING!  Be sure that your topic and key strings don't try
        # to interpolate out a field in event that doesn't exist!
        if isinstance(event, dict):
            message_topic = (topic % event).encode('utf8')
            message_key = (key % event).encode('utf8')
        else:
            message_topic = default_topic
            message_key = default_key

        try:
            # Make sure this topic exists before we attempt to produce to it.
            # This call will timeout in kafka_topic_create_timeout_seconds.
            # This should return faster than this if this kafka client has
            # already cached topic metadata for this topic.  Otherwise
            # it will try to ask Kafka for it each time.  Make sure
            # auto.create.topics.enabled is true for your Kafka cluster!
            kafka.ensure_topic_exists(
                message_topic,
                kafka_topic_create_timeout_seconds
            )
        except KafkaTimeoutError:
            error_message = "Failed to ensure Kafka topic %s exists " \
                "in %f seconds when producing event" % (
                    message_topic,
                    kafka_topic_create_timeout_seconds
                )
            if isinstance(event, dict):
                error_message += " of schema %s revision %d" % (
                    event['schema'],
                    event['revision']
                )
            error_message += ". Skipping event. " \
                "(This might be ok if this is a new topic.)"
            logging.error(error_message)
            continue

        if raw:
            value = event.encode('utf-8')
        else:
            value = json.dumps(event, sort_keys=True)

        producer.send(message_topic, message_key, value)


@writes('mysql', 'sqlite')
def sql_writer(uri, replace=False):
    """Writes to an RDBMS, creating tables for SCIDs and rows for events."""
    # Don't pass 'replace' parameter to SQLAlchemy.
    uri = uri_delete_query_item(uri, 'replace')
    logger = logging.getLogger('Log')
    meta = sqlalchemy.MetaData(bind=uri)
    # Each scid stores a buffer and the timestamp of the first insertion.
    events = collections.defaultdict(lambda: ([], time.time()))
    events_batch = collections.deque()
    worker = PeriodicThread(interval=DB_FLUSH_INTERVAL,
                            target=store_sql_events,
                            args=(meta, events_batch),
                            kwargs={'replace': replace})
    worker.start()

    if meta.bind.dialect.name == 'mysql':
        @sqlalchemy.event.listens_for(sqlalchemy.pool.Pool, 'checkout')
        def ping(dbapi_connection, connection_record, connection_proxy):
            # Just before executing an insert, call mysql_ping() to verify
            # that the connection is alive, and reconnect if necessary.
            dbapi_connection.ping(True)
    try:
        batch_size = 400
        batch_time = 300  # in seconds
        # Link the main thread to the worker thread so we
        # don't keep filling the queue if the worker died.
        while worker.is_alive():
            event = (yield)
            # Break the event stream by schema (and revision)
            scid = (event['schema'], event['revision'])
            scid_events, first_timestamp = events[scid]
            scid_events.append(event)
            # Check if the schema queue is too long or too old
            if (len(scid_events) >= batch_size or
                    time.time() - first_timestamp >= batch_time):
                logger.debug('%s_%s queue is large or old, flushing', *scid)
                events_batch.append((scid, scid_events))
                del events[scid]
    except GeneratorExit:
        # Allow the worker to complete any work that is
        # already in progress before shutting down.
        logger.debug('Stopped main thread via GeneratorExit')
        logger.debug('Events when stopped %s', len(events))
        worker.stop()
        worker.join()
    except Exception:
        t = traceback.format_exc()
        logger.debug('Exception caught %s', t)
        raise
    finally:
        # If there are any events remaining in the queue,
        # process them in the main thread before exiting.
        for scid, (scid_events, _) in events.iteritems():
            events_batch.append((scid, scid_events))
        store_sql_events(meta, events_batch, replace=replace)


@writes('file')
def log_writer(path, raw=False):
    """Write events to a file on disk."""
    handler = logging.handlers.WatchedFileHandler(path)

    # We want to be able to support multiple file writers
    # within a given Python process, so uniquely
    # identify this logger within Python's logging
    # system by the file's path.
    log = logging.getLogger('Events-' + path)

    log.setLevel(logging.INFO)
    log.addHandler(handler)
    # Don't propagate these events to the global logger
    # used by eventlogging.  We don't want eventlogging
    # daemons to print these event logs to stdout or stderr
    # all the time.
    log.propagate = False

    while 1:
        event = (yield)
        if raw:
            log.info(event)
        else:
            log.info(json.dumps(event, sort_keys=True, check_circular=False))


@writes('tcp')
def zeromq_writer(uri, raw=False):
    """Publish events on a ZeroMQ publisher socket."""
    pub = pub_socket(uri)
    while 1:
        event = (yield)
        if raw:
            pub.send_unicode(event)
        else:
            pub.send_unicode(json.dumps(event,
                                        sort_keys=True,
                                        check_circular=False) + '\n')


@writes('statsd')
def statsd_writer(hostname, port, prefix='eventlogging.schema'):
    """Increments StatsD SCID counters for each event."""
    addr = socket.gethostbyname(hostname), port
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    while 1:
        event = (yield)
        stat = prefix + '.%(schema)s:1|c' % event
        sock.sendto(stat.encode('utf-8'), addr)


@writes('stdout')
def stdout_writer(uri, raw=False):
    """Writes events to stdout. Pretty-prints if stdout is a terminal."""
    dumps_kwargs = dict(sort_keys=True, check_circular=False)
    if sys.stdout.isatty():
        dumps_kwargs.update(indent=2)
    while 1:
        event = (yield)
        if raw:
            print(event)
        else:
            print(json.dumps(event, **dumps_kwargs))


@writes('udp')
def udp_writer(hostname, port, raw=False):
    """Writes data to UDP."""
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    while 1:
        event = (yield)
        if raw:
            sock.sendto(event, (hostname, port))
        else:
            sock.sendto(json.dumps(event), (hostname, port))

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
def udp_reader(hostname, port, raw=False):
    """Reads data from a UDP socket."""
    return stream(udp_socket(hostname, port), raw)


@reads('kafka')
def kafka_reader(
    path,
    topic='eventlogging',
    group_id='eventlogging',
    raw=False
):
    """Reads events from Kafka"""
    from kafka import KafkaConsumer

    # Brokers should be in the uri path
    brokers = path.strip('/')

    consumer = KafkaConsumer(
        topic,
        group_id=group_id,
        metadata_broker_list=brokers
    )
    return stream((message.value for message in consumer), raw)
