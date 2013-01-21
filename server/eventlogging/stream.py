# -*- coding: utf-8 -*-
"""
  eventlogging.stream
  ~~~~~~~~~~~~~~~~~~~

  This module provides helpers for reading from and writing to ZeroMQ
  data streams using ZeroMQ or UDP.

"""
from __future__ import unicode_literals

import io
import time

import zmq


def zmq_subscribe(endpoint, topic='', sid=None, json=False):
    """Generator; yields lines from ZMQ_SUB socket."""
    context = zmq.Context.instance()
    sock = context.socket(zmq.SUB)
    if sid is not None:
        sock.setsockopt(zmq.IDENTITY, sid.encode('utf8'))
    sock.connect(endpoint)
    sock.setsockopt(zmq.SUBSCRIBE, topic.encode('utf8'))
    recv = sock.recv_json if json else sock.recv_unicode

    while 1:
        yield recv()


def tail_follow(filename):
    """Generator; equivalent to tail -f."""
    with io.open(filename, encoding='utf8', errors='replace') as f:
        f.seek(0, 2)
        while 1:
            line = f.readline()
            if not line:
                time.sleep(0.1)
                continue
            yield line
