# -*- coding: utf8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains test fixtures.

"""
from __future__ import unicode_literals

import copy

import eventlogging
import sqlalchemy


TEST_SCHEMA_SCID = ('TestSchema', 123)

_schemas = {
    eventlogging.schema.CAPSULE_SCID: {
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
            },
            'nested': {
                'type': 'object',
                'properties': {
                    'deeplyNested': {
                        'type': 'object',
                        'properties': {
                            'pi': {
                                'type': 'number',
                            }
                        }
                    }
                }
            }
        }
    }
}


_event = {
    'event': {
        'value': '☆ 彡',
        'nested': {
            'deeplyNested': {
                'pi': 3.14159
            }
        }
    },
    'seqId': 12345,
    'clientIp': '127.0.0.1',
    'timestamp': 1358791834912,
    'isTruncated': False,
    'wiki': 'enwiki',
    'webHost': 'en.m.wikipedia.org',
    'recvFrom': 'fenari',
    'isValid': True,
    'revision': 123,
    'schema': 'TestSchema'
}


class HttpRequestAttempted(RuntimeError):
    """Raised on attempt to retrieve a schema via HTTP."""
    pass


# We'll be replacing :func:`eventlogging.schemas.http_get_schema` with a
# mock object, so set aside an unpatched copy so we can clean up.
orig_http_get_schema = eventlogging.schema.http_get_schema


def mock_http_get_schema(scid):
    """Mock of :func:`eventlogging.schemas.http_get_schema`
    Used to detect when :func:`eventlogging.schemas.get_schema`
    delegates to HTTP retrieval.
    """
    raise HttpRequestAttempted('Attempted HTTP fetch: %s' % (scid,))


class SchemaTestMixin(object):
    """A :class:`unittest.TestCase` mix-in for test cases that depend on
    schema look-ups."""

    def setUp(self):
        self.event = copy.deepcopy(_event)
        eventlogging.schema.schema_cache = copy.deepcopy(_schemas)
        eventlogging.schema.http_get_schema = mock_http_get_schema

    def tearDown(self):
        eventlogging.schema.schema_cache.clear()
        eventlogging.schema.http_get_schema = orig_http_get_schema

    def assertIsValid(self, event, msg=None):
        return self.assertIsNone(eventlogging.validate(event), msg)

    def assertIsInvalid(self, event, msg=None):
        with self.assertRaises(eventlogging.ValidationError, msg):
            eventlogging.validate(event)


class DatabaseTestMixin(SchemaTestMixin):
    """A :class:`unittest.TestCase` mix-in for database testing using an
    in-memory sqlite database."""

    def setUp(self):
        super(DatabaseTestMixin, self).setUp()
        # Add an ``echo=True`` kwarg to ``create_engine`` below to debug SQL:
        self.engine = sqlalchemy.create_engine('sqlite:///:memory:')
        self.meta = sqlalchemy.MetaData(bind=self.engine)
