# -*- coding: utf-8 -*-
"""
  eventlogging.stream
  ~~~~~~~~~~~~~~~~~~~

  This module provides helpers for reading from and writing to ZeroMQ
  data streams using ZeroMQ or UDP.

"""
from __future__ import unicode_literals

import re

import zmq
from eventlogging.compat import items


__all__ = ('pub_socket', 'sub_socket', 'iter_socket', 'iter_socket_json',
           'make_canonical')

#: High water mark. The maximum number of outstanding messages to queue in
#: memory for any single peer that the socket is communicating with.
ZMQ_HIGH_WATER_MARK = 3000

#: If a socket is closed before all its messages has been sent, ØMQ will
#: wait up to this many miliseconds before discarding the messages.
#: We'd rather fail fast, even at the cost of dropping a few events.
ZMQ_LINGER = 0

#: The maximum socket buffer size in bytes. This is used to set either
#: SO_SNDBUF or SO_RCVBUF for the underlying socket, depending on its
#: type. We set it to 64 kB to match Udp2LogConfig::BLOCK_SIZE.
SOCKET_BUFFER_SIZE = 64 * 1024


def pub_socket(endpoint):
    """Get a pre-configured ØMQ publisher."""
    context = zmq.Context.instance()
    socket = context.socket(zmq.PUB)
    if hasattr(zmq, 'HWM'):
        socket.hwm = ZMQ_HIGH_WATER_MARK
    socket.linger = ZMQ_LINGER
    socket.sndbuf = SOCKET_BUFFER_SIZE
    canonical_endpoint = make_canonical(endpoint, host='*')
    socket.bind(canonical_endpoint)
    return socket


def sub_socket(endpoint, identity='', subscribe=''):
    """Get a pre-configured ØMQ subscriber."""
    context = zmq.Context.instance()
    socket = context.socket(zmq.SUB)
    if hasattr(zmq, 'HWM'):
        socket.hwm = ZMQ_HIGH_WATER_MARK
    socket.linger = ZMQ_LINGER
    socket.rcvbuf = SOCKET_BUFFER_SIZE
    if identity:
        socket.identity = identity.encode('utf-8')
    canonical_endpoint = make_canonical(endpoint)
    socket.connect(canonical_endpoint)
    socket.subscribe = subscribe.encode('utf-8')
    return socket


def iter_socket(socket):
    """Iterator; read and decode unicode strings from a socket."""
    return iter(socket.recv_unicode, None)


def iter_socket_json(socket):
    """Iterator; read and decode successive JSON objects from a socket."""
    return iter(socket.recv_json, None)


def make_canonical(uri, protocol='tcp', host='127.0.0.1'):
    """Convert a partial endpoint URI to a fully canonical one, using
    TCP and localhost as the default protocol and host. The partial URI
    must at minimum contain a port number."""
    fragments = dict(protocol=protocol, host=host)
    match = re.match(r'((?P<protocol>[^:]+)://)?((?P<host>[^:]+):)?'
                     r'(?P<port>\d+)(?:\?.*)?', '%s' % uri)
    fragments.update((k, v) for k, v in items(match.groupdict()) if v)
    return '%(protocol)s://%(host)s:%(port)s' % dict(fragments)
