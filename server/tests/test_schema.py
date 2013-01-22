# -*- coding: utf8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains tests for :module:`eventlogging.schema`.

  This does not test JSON Schema validation per se, since validation
  is delegated to  :module:`jsonschema`, which comes with its own
  comprehensive suite of unit tests. This module specifically tests
  schema cache / HTTP retrieval and the handling of complex
  ('encapsulated') event objects.

"""
import copy
import unittest

import eventlogging


test_schemas = {
    eventlogging.schema.META_REV_ID: {
        'properties': {
            'clientIp': {
                'type': 'string'
            },
            'event': {
                'type': 'object',
                'required': True
            },
            'isTruncated': {
                'type': 'boolean'
            },
            'isValid': {
                'type': 'boolean'
            },
            'wiki': {
                'type': 'string',
                'required': True
            },
            'webHost': {
                'type': 'string'
            },
            'revision': {
                'type': 'integer',
                'required': True
            },
            'schema': {
                'type': 'string',
                'required': True
            },
            'recvFrom': {
                'type': 'string',
                'required': True
            },
            'seqId': {
                'type': 'integer'
            },
            'timestamp': {
                'type': 'number',
                'required': True
            }
        }
    },
    -1: {
        'properties': {
            'value': {
                'type': 'string',
                'required': True
            }
        }
    }
}


test_event = {
    'event': {
        'value': 'Test event',
    },
    'seqId': 12345,
    'clientIp': '127.0.0.1',
    'timestamp': 1358791834912,
    'isTruncated': False,
    'wiki': 'enwiki',
    'recvFrom': 'fenari',
    'isValid': True,
    'revision': -1,
    'schema': 'TestSchema'
}


class HttpRequestAttempted(RuntimeError):
    """Raised on attempt to retrieve a schema via HTTP."""

    def __init__(self, message, rev_id):
        self.message = message
        self.rev_id = rev_id


# We'll be replacing :func:`eventlogging.schemas.http_get_schema` with a
# mock object, so set aside an unpatched copy so we can clean up.
orig_http_get_schema = eventlogging.schema.http_get_schema


def mock_http_get_schema(rev_id):
    """Mock of :func:`eventlogging.schemas.http_get_schema`
    Used to detect when :func:`eventlogging.schemas.get_schema`
    delegates to HTTP retrieval.
    """
    raise HttpRequestAttempted('Attempt to fetch schema via HTTP', rev_id)


class SchemaTestCase(unittest.TestCase):

    def setUp(self):
        self.event = copy.deepcopy(test_event)
        eventlogging.schema._schemas = copy.deepcopy(test_schemas)
        eventlogging.schema.http_get_schema = mock_http_get_schema

    def tearDown(self):
        eventlogging.schema._schemas = {}
        eventlogging.schema.http_get_schema = orig_http_get_schema

    def assertIsValid(self, event, msg=None):
        return self.assertIsNone(eventlogging.validate(event), msg)

    def assertIsInvalid(self, event, msg=None):
        with self.assertRaises(eventlogging.ValidationError, msg):
            eventlogging.validate(event)

    def test_valid_event(self):
        """Valid events validate."""
        self.assertIsValid(self.event)

    def test_missing_property(self):
        """Missing property in meta object triggers validation failure."""
        self.event.pop('timestamp')
        self.assertIsInvalid(self.event)

    def test_missing_nested_property(self):
        """Missing property in nested event triggers validation failure."""
        self.event['event'].pop('value')
        self.assertIsInvalid(self.event)

    def test_schema_retrieval(self):
        """Schemas missing from the cache are retrieved via HTTP."""
        eventlogging.schema._schemas.pop(-1)  # Pop the schema from the cache.
        with self.assertRaises(HttpRequestAttempted) as context:
            eventlogging.validate(self.event)
            self.assertEqual(context.exception.rev_id, -1)
