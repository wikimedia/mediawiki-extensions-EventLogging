# -*- coding: utf-8 -*-
"""
  eventlogging.stream
  ~~~~~~~~~~~~~~~~~~~

  This module provides helpers for reading from and writing to ZeroMQ
  data streams using ZeroMQ or UDP.

"""
from __future__ import unicode_literals

import io
import json
import re
import socket

import zmq

from .compat import items, urlparse


__all__ = ('pub_socket', 'sub_socket', 'udp_socket', 'iter_socket',
           'iter_socket_json', 'make_canonical')

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
    if identity and hasattr(socket, 'identity'):
        socket.identity = identity.encode('utf-8')
    canonical_endpoint = make_canonical(endpoint)
    socket.connect(canonical_endpoint)
    socket.subscribe = subscribe.encode('utf-8')
    return socket


def udp_socket(endpoint):
    """Parse a URI and configure a UDP socket for it."""
    canonical_endpoint = make_canonical(endpoint, host='0.0.0.0')
    ip, port = urlparse(canonical_endpoint).netloc.split(':')
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    sock.bind((ip, int(port)))
    return sock


def iter_file(file):
    """Wrap a file object's underlying file descriptor with a UTF8 line
    reader and read successive lines."""
    with io.open(file.fileno(), mode='rt', encoding='utf8',
                 errors='replace') as f:
        for line in f:
            yield line


def iter_socket(socket):
    """Iterator; read and decode unicode strings from a socket."""
    if hasattr(socket, 'recv_unicode'):
        return iter(socket.recv_unicode, None)
    return iter_file(socket)


def iter_socket_json(socket):
    """Iterator; read and decode successive JSON objects from a socket."""
    if hasattr(socket, 'recv_json'):
        return iter(socket.recv_json, None)
    return (json.loads(dgram) for dgram in iter_socket(socket))


def make_canonical(uri, protocol='tcp', host='127.0.0.1'):
    """Convert a partial endpoint URI to a fully canonical one, using
    TCP and localhost as the default protocol and host. The partial URI
    must at minimum contain a port number."""
    fragments = dict(protocol=protocol, host=host)
    match = re.match(r'((?P<protocol>[^:]+)://)?((?P<host>[^:]+):)?'
                     r'(?P<port>\d+)(?:\?.*)?', '%s' % uri)
    fragments.update((k, v) for k, v in items(match.groupdict()) if v)
    return '%(protocol)s://%(host)s:%(port)s' % dict(fragments)
