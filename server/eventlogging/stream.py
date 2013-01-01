# -*- coding: utf-8 -*-
"""
  EventLogging
  ~~~~~~~~~~~~

  This module provides helpers for reading from and writing to ZeroMQ
  data streams using ZeroMQ or UDP.

  :copyright: (c) 2012 by Ori Livneh <ori@wikimedia.org>
  :license: GNU General Public Licence 2.0 or later

"""
from __future__ import unicode_literals

import io
import socket
import time

from .compat import iter_socket

import zmq


def zmq_subscribe(endpoint, topic='', json=False):
    """Generator; yields lines from ZMQ_SUB socket."""
    context = zmq.Context.instance()
    sock = context.socket(zmq.SUB)
    sock.connect(endpoint)
    sock.setsockopt_string(zmq.SUBSCRIBE, topic)
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


def iter_udp(port, iface='0.0.0.0'):
    """Line-based iterator on incoming UDP stream."""
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    sock.bind((iface, port))
    for line in iter_socket(sock):
        yield line
