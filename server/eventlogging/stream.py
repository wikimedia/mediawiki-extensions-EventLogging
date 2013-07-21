# -*- coding: utf-8 -*-
"""
  eventlogging.stream
  ~~~~~~~~~~~~~~~~~~~

  This module provides helpers for reading from and writing to ZeroMQ
  data streams using ZeroMQ or UDP.

"""
from __future__ import unicode_literals

import zmq


#: Default socket buffer size. Corresponds to Udp2LogConfig::BLOCK_SIZE.
BLOCK_SIZE = 65536

#: Default value for ZeroMQ's 'high-water mark' socket option, which
#: caps the number of messages which a socket will buffer before
#: dropping data or blocking.
HWM = 1000

#: Default value for zmq.LINGER, in ms.
LINGER = 1000


__all__ = ('zmq_subscribe', 'BLOCK_SIZE', 'HWM', 'LINGER')


def zmq_subscribe(endpoint, topic='', sid=None, json=False):
    """Generator; yields lines from ZMQ_SUB socket."""
    context = zmq.Context.instance()
    sock = context.socket(zmq.SUB)
    if sid is not None:
        sock.identity = sid.encode('utf8')
    sock.hwm = HWM
    sock.linger = LINGER
    sock.setsockopt(zmq.RCVBUF, BLOCK_SIZE)
    sock.connect(endpoint)
    sock.setsockopt(zmq.SUBSCRIBE, topic.encode('utf8'))
    recv = sock.recv_json if json else sock.recv_unicode

    while 1:
        yield recv()
