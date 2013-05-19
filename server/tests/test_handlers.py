# -*- coding: utf8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains tests for :module:`eventlogging.jrm`.

"""
from __future__ import unicode_literals

import unittest

import eventlogging
import eventlogging.base


def echo_writer(uri, **kwargs):
    values = []
    while 1:
        value = (yield values)
        values.append(value)


def repeater(uri, value, **kwargs):
    while 1:
        yield value


class HandlerFactoryTestCase(unittest.TestCase):
    """Test case for URI-based reader/writer factories."""

    def setUp(self):
        """Register test handlers."""
        eventlogging.writes('test')(echo_writer)
        eventlogging.reads('test')(repeater)

    def tearDown(self):
        """Unregister test handlers."""
        eventlogging.base._writers.pop('test')
        eventlogging.base._readers.pop('test')

    def test_get_writer(self):
        """``get_writer`` returns a scheme-appropriate primed coroutine."""
        writer = eventlogging.get_writer('test://localhost')
        self.assertEqual(writer.send(123), [123])

    def test_get_reader(self):
        """``get_reader`` returns the right generator for the URI scheme."""
        reader = eventlogging.get_reader('test://localhost/?value=secret')
        self.assertEqual(next(reader), 'secret')
