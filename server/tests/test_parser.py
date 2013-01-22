# -*- coding: utf8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains tests for :class:`eventlogging.LogParser`.

"""
import unittest

import eventlogging


# Control data for parser test cases.
parser_cases = (
    {
        'description': 'server-side events',
        'format': '%n EventLogging %j',
        'raw': ('99 EventLogging {"revision":123,"timestamp":1358627115,"sche'
                'ma":"FakeSchema","isValid":true,"wiki":"enwiki","event":{"ac'
                'tion":"save\\u0020page"},"recvFrom":"fenari"}'),
        'parsed': {
            'recvFrom': 'fenari',
            'timestamp': 1358627115,
            'wiki': 'enwiki',
            'seqId': 99,
            'schema': 'FakeSchema',
            'isValid': True,
            'revision': 123,
            'event': {
                'action': 'save page'
            },
        },
    },
    {
        'description': 'client-side events',
        'format': '%q %l %n %t %h',
        'raw': ('?%7B%22wiki%22%3A%22testwiki%22%2C%22schema%22%3A%22Generic'
                '%22%2C%22revision%22%3A13%2C%22isValid%22%3Atrue%2C%22event'
                '%22%3A%7B%22articleId%22%3A1%2C%22articleTitle%22%3A%22Main'
                '%20Page%22%7D%2C%22webHost%22%3A%22test.wikipedia.org%22%7D'
                '; cp3022.esams.wikimedia.org 132073 2013-01-19T23:16:38 86.'
                '149.229.149'),
        'parsed': {
            'recvFrom': 'cp3022.esams.wikimedia.org',
            'isValid': True,
            'wiki': 'testwiki',
            'webHost': 'test.wikipedia.org',
            'seqId': 132073,
            'timestamp': 1358637398,
            'clientIp': eventlogging.hash_value('86.149.229.149'),
            'schema': 'Generic',
            'revision': 13,
            'event': {
                'articleTitle': 'Main Page',
                'articleId': 1
            },
        },
    },
)


class LogParserTestCase(unittest.TestCase):
    """Test case for LogParser."""
    def runTest(self):
        parser = eventlogging.LogParser(self.format)
        self.assertEqual(parser.parse(self.raw), self.parsed)

    def shortDescription(self):
        return 'LogParser: %s (%s)' % (self.description, self.format)


def load_tests(loader, tests, pattern):
    """Called by unit test."""
    suite = unittest.TestSuite()
    for case in parser_cases:
        testcase = LogParserTestCase()
        testcase.__dict__.update(case)
        suite.addTest(testcase)
    return suite
