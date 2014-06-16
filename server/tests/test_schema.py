# -*- coding: utf-8 -*-
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
        self.http_resp = '{"properties":{"value":{"type":"number"}}}'
        schema = eventlogging.schema.http_get_schema(TEST_SCHEMA_SCID)
        self.assertEqual(schema, {'properties': {'value': {'type': 'number'}}})

    def test_invalid_resp(self):
        """Test handling of HTTP response not containing valid schema."""
        self.http_resp = '"foo"'
        with self.assertRaises(eventlogging.SchemaError):
            eventlogging.schema.http_get_schema(TEST_SCHEMA_SCID)

    def test_caching(self):
        """Valid HTTP responses containing JSON Schema are cached."""
        self.http_resp = '{"properties":{"value":{"type":"number"}}}'
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

    def test_empty_event(self):
        """An empty event with no mandatory properties should validate"""
        self.assertIsValid(self.incorrectly_serialized_empty_event)


class PostValidationFixupsTestCase(unittest.TestCase):
    """Tests for :module:`eventlogging.schema`s post validation fix-ups."""

    def test_delete_if_exists_and_length_mismatches_simple_match(self):
        """Validate a simple match of key and length."""
        capsule = {
            'event': {
                'foo': 'qux',
            }
        }

        eventlogging.schema.delete_if_exists_and_length_mismatches(
            capsule, 'foo', 3)

        self.assertEqual(capsule, {
            'event': {
                'foo': 'qux',
            }
        })

    def test_delete_if_exists_and_length_mismatches_match(self):
        """Validate a match for a not totally trivial capsule"""
        capsule = {
            'event': {
                'foo': 'qux',
                'bar': 'quux',
                'baz': 'quuz',
            },
            'bar': 'quz',
        }

        eventlogging.schema.delete_if_exists_and_length_mismatches(
            capsule, 'bar', 4)

        self.assertEqual(capsule, {
            'event': {
                'foo': 'qux',
                'bar': 'quux',
                'baz': 'quuz',
            },
            'bar': 'quz',
        })

    def test_delete_if_exists_and_length_mismatches_non_existing(self):
        """Validates that non existing keys are accepted."""
        capsule = {'event': {}}

        eventlogging.schema.delete_if_exists_and_length_mismatches(
            capsule, 'non_existing_field', 2)

        self.assertEqual(capsule, {'event': {}})

    def test_delete_if_exists_and_length_mismatches_different_length(self):
        """Validates removal of correct key upon length mismatch."""
        capsule = {
            'event': {
                'foo': 'qux',
                'bar': 'quux',
                'baz': 'quux',
            },
            'bar': 'quz',
        }

        eventlogging.schema.delete_if_exists_and_length_mismatches(
            capsule, 'bar', 3)

        self.assertEqual(capsule, {
            'event': {
                'foo': 'qux',
                'baz': 'quux',
            },
            'bar': 'quz',
        })
