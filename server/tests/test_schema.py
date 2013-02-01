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
from __future__ import unicode_literals

import copy
import unittest
import uuid

import eventlogging


TEST_SCHEMA_SCID = ('TestSchema', -1)

test_schemas = {
    eventlogging.CAPSULE_SCID: {
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
        },
        'additionalProperties': False
    },
    TEST_SCHEMA_SCID: {
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


def mock_http_get_schema(scid):
    """Mock of :func:`eventlogging.schemas.http_get_schema`
    Used to detect when :func:`eventlogging.schemas.get_schema`
    delegates to HTTP retrieval.
    """
    raise HttpRequestAttempted('Attempt to fetch schema via HTTP: %s', (scid,))


class SchemaTestCase(unittest.TestCase):

    def setUp(self):
        self.event = copy.deepcopy(test_event)
        eventlogging.schema.schema_cache = copy.deepcopy(test_schemas)
        eventlogging.schema.http_get_schema = mock_http_get_schema

    def tearDown(self):
        eventlogging.schema.schema_cache.clear()
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
        """Missing property in capsule object triggers validation failure."""
        self.event.pop('timestamp')
        self.assertIsInvalid(self.event)

    def test_missing_nested_property(self):
        """Missing property in nested event triggers validation failure."""
        self.event['event'].pop('value')
        self.assertIsInvalid(self.event)

    def test_extra_property(self):
        """Missing property in nested event triggers validation failure."""
        self.event['event']['season'] = 'summer'
        self.assertIsInvalid(self.event)

    def test_schema_retrieval(self):
        """Schemas missing from the cache are retrieved via HTTP."""
        # Pop the schema from the cache.
        eventlogging.schema.schema_cache.pop(TEST_SCHEMA_SCID)
        with self.assertRaises(HttpRequestAttempted) as context:
            eventlogging.validate(self.event)
            self.assertEqual(context.exception.rev_id, -1)

    def test_encapsulated_schema(self):
        """get_schema() returns encapsulated schema if requested."""
        encapsulated = eventlogging.get_schema(eventlogging.CAPSULE_SCID)
        encapsulated['event'] = eventlogging.get_schema(TEST_SCHEMA_SCID)
        self.assertEqual(eventlogging.get_schema(TEST_SCHEMA_SCID, True),
                         encapsulated)

    def test_capsule_uuid(self):
        """capsule_uuid() generates a unique UUID for capsule objects."""
        self.assertEqual(eventlogging.capsule_uuid(test_event),
                         uuid.UUID(hex='babb66f34a0a5de3be0c6513088be33e'))
