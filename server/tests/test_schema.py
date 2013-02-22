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

import unittest

import eventlogging

from .fixtures import (
    HttpRequestAttempted,
    HttpSchemaTestMixin,
    SchemaTestMixin,
    TEST_SCHEMA_SCID
)


class HttpSchemaTestCase(HttpSchemaTestMixin, unittest.TestCase):
    """Tests for :func:`eventlogging.schema.http_get_schema`."""

    def test_valid_resp(self):
        """Test handling of HTTP response containing valid schema."""
        self.http_resp = b'{"properties":{"value":{"type":"number"}}}'
        schema = eventlogging.schema.http_get_schema(TEST_SCHEMA_SCID)
        self.assertEqual(schema, {'properties': {'value': {'type': 'number'}}})

    def test_invalid_resp(self):
        """Test handling of HTTP response not containing valid schema."""
        self.http_resp = b'"foo"'
        with self.assertRaises(eventlogging.SchemaError):
            eventlogging.schema.http_get_schema(TEST_SCHEMA_SCID)

    def test_caching(self):
        """Valid HTTP responses containing JSON Schema are cached."""
        self.http_resp = b'{"properties":{"value":{"type":"number"}}}'
        eventlogging.get_schema(TEST_SCHEMA_SCID)
        self.assertIn(TEST_SCHEMA_SCID, eventlogging.schema.schema_cache)


class SchemaTestCase(SchemaTestMixin, unittest.TestCase):
    """Tests for :module:`eventlogging.schema`."""

    def test_valid_event(self):
        """Valid events validate."""
        self.assertIsValid(self.event)

    def test_incomplete_scid(self):
        """Missing SCID in capsule object triggers validation failure."""
        self.event.pop('schema')
        self.assertIsInvalid(self.event)

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
            self.assertEqual(context.exception.rev_id, 123)

    def test_encapsulated_schema(self):
        """get_schema() returns encapsulated schema if requested."""
        encapsulated = eventlogging.get_schema(eventlogging.CAPSULE_SCID)
        encapsulated['event'] = eventlogging.get_schema(TEST_SCHEMA_SCID)
        self.assertEqual(eventlogging.get_schema(TEST_SCHEMA_SCID, True),
                         encapsulated)

    def test_capsule_uuid(self):
        """capsule_uuid() generates a unique UUID for capsule objects."""
        self.assertEqual(eventlogging.capsule_uuid(self.event),
                         'babb66f34a0a5de3be0c6513088be33e')
