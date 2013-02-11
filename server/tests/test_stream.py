# -*- coding: utf8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains tests for :module:`eventlogging.stream`.

"""
from __future__ import unicode_literals

import multiprocessing
import time
import unittest

import eventlogging
import zmq

from .fixtures import TimeoutTestMixin


def publish(pipe, interface='tcp://127.0.0.1'):
    """Listen on a :class:`multiprocessing.Pipe` and publish incoming
    messages over a :obj:`zmq.PUB` socket. Terminate upon receiving
    empty string."""
    context = zmq.Context()
    pub = context.socket(zmq.PUB)
    pub.setsockopt(zmq.LINGER, 0)
    port = pub.bind_to_random_port(interface)

    # Let the other party know what endpoint we are bound to.
    pipe.send('%s:%s' % (interface, port))

    for message in iter(pipe.recv, ''):
        time.sleep(0.05)
        pub.send_unicode(message)


class ZmqTestCase(TimeoutTestMixin, unittest.TestCase):
    """Test case for ZeroMQ-related functionality."""

    def setUp(self):
        """Spin up a worker subprocess that will publish anything we
        pipe into it."""
        self.pipe, other_pipe = multiprocessing.Pipe()
        publisher = multiprocessing.Process(target=publish, args=[other_pipe])
        publisher.daemon = True
        publisher.start()
        self.addCleanup(publisher.terminate)
        self.endpoint = self.pipe.recv()
        super(ZmqTestCase, self).setUp()

    def tearDown(self):
        """Send kill sentinel to worker subprocess."""
        self.pipe.send('')
        super(ZmqTestCase, self).tearDown()

    def test_zmq_subscribe(self):
        """zmq_subscribe(...) receives string objects."""
        subscriber = eventlogging.stream.zmq_subscribe(self.endpoint)
        self.pipe.send('Hello.')
        self.assertEqual(next(subscriber), 'Hello.')

    def test_zmq_subscribe_json(self):
        """zmq_subscribe(..., json=True) decodes JSON messages."""
        subscriber = eventlogging.zmq_subscribe(
            self.endpoint, sid=self.id(), json=True)
        self.pipe.send('{"message":"secret"}')
        self.assertEqual(next(subscriber), dict(message='secret'))
