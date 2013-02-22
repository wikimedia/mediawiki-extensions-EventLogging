# -*- coding: utf8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains tests for :class:`eventlogging.LogParser`.

"""
from __future__ import unicode_literals

import calendar
import datetime
import unittest

import eventlogging


class NcsaTimestampTestCase(unittest.TestCase):
    """Test case for converting to or from NCSA Common Log format."""

    def test_ncsa_timestamp_handling(self):
        epoch_ts = calendar.timegm(datetime.datetime.utcnow().utctimetuple())
        ncsa_ts = eventlogging.ncsa_utcnow()
        self.assertAlmostEqual(eventlogging.ncsa_to_epoch(ncsa_ts),
                               epoch_ts, delta=100)


class LogParserTestCase(unittest.TestCase):
    """Test case for LogParser."""

    maxDiff = None

    def test_parse_client_side_events(self):
        """Parser test: client-side events (%q %l %n %t %h)."""
        parser = eventlogging.LogParser('%q %l %n %t %h')
        raw = ('?%7B%22wiki%22%3A%22testwiki%22%2C%22schema%22%3A%22Generic'
               '%22%2C%22revision%22%3A13%2C%22clientValidated%22%3Atrue%2C'
               '%22event%22%3A%7B%22articleId%22%3A1%2C%22articleTitle%22%3'
               'A%22H%C3%A9ctor%20Elizondo%22%7D%2C%22webHost%22%3A%22test.'
               'wikipedia.org%22%7D; cp3022.esams.wikimedia.org 132073 2013'
               '-01-19T23:16:38 86.149.229.149')
        parsed = {
            'recvFrom': 'cp3022.esams.wikimedia.org',
            'clientValidated': True,
            'wiki': 'testwiki',
            'webHost': 'test.wikipedia.org',
            'seqId': 132073,
            'timestamp': 1358637398,
            'clientIp': eventlogging.parse.hash_value('86.149.229.149'),
            'schema': 'Generic',
            'revision': 13,
            'event': {
                'articleTitle': 'Héctor Elizondo',
                'articleId': 1
            }
        }
        self.assertEqual(parser.parse(raw), parsed)

    def test_parser_server_side_events(self):
        """Parser test: server-side events (%n EventLogging %j)."""
        parser = eventlogging.LogParser('%n EventLogging %j')
        raw = ('99 EventLogging {"revision":123,"timestamp":1358627115,"sche'
               'ma":"FakeSchema","clientValidated":true,"wiki":"enwiki","eve'
               'nt":{"action":"save\\u0020page"},"recvFrom":"fenari"}')
        parsed = {
            'recvFrom': 'fenari',
            'timestamp': 1358627115,
            'wiki': 'enwiki',
            'seqId': 99,
            'schema': 'FakeSchema',
            'clientValidated': True,
            'revision': 123,
            'event': {
                'action': 'save page'
            }
        }
        self.assertEqual(parser.parse(raw), parsed)

    def test_parse_failure(self):
        """Parse failure raises ValueError exception."""
        parser = eventlogging.LogParser('%q %l %n %t %h')
        with self.assertRaises(ValueError):
            parser.parse('Fails to parse.')

    def test_repr(self):
        """Calling 'repr' on LogParser returns canonical string
        representation."""
        parser = eventlogging.LogParser('%q %l %n %t %h')
        self.assertEqual(repr(parser), "<LogParser('%q %l %n %t %h')>")
