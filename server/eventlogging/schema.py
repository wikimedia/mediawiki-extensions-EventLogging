# -*- coding: utf-8 -*-
"""
  eventlogging.schema
  ~~~~~~~~~~~~~~~~~~~

  This module implements schema retrieval and validation. Schemas are
  referenced via SCIDs, which are tuples of (Schema name, Revision ID).
  Schemas are retrieved via HTTP and then cached in-memory. Validation
  uses :module:`jsonschema`.

"""
from __future__ import unicode_literals

import re

import jsonschema

from .compat import integer_types, json, http_get, string_types

__all__ = ('CAPSULE_SCID', 'get_schema', 'SCHEMA_URL_FORMAT', 'validate')


# Regular expression which matches valid schema names.
SCHEMA_RE = re.compile(r'^[a-zA-Z0-9_-]{1,63}$')

# URL of index.php on the schema wiki (same as
# '$wgEventLoggingSchemaApiUri').
SCHEMA_WIKI_API = 'http://meta.wikimedia.org/w/api.php'

# Template for schema article URLs. Interpolates SCIDs.
SCHEMA_URL_FORMAT = SCHEMA_WIKI_API + '?action=jsonschema&title=%s&revid=%s'

# Schemas retrieved via HTTP are cached in this dictionary.
schema_cache = {}

# SCID of the metadata object which wraps each event.
CAPSULE_SCID = ('EventCapsule', 10981547)


def get_schema(scid, encapsulate=False):
    """Get schema from memory or HTTP."""
    schema = schema_cache.get(scid)
    if schema is None:
        schema = http_get_schema(scid)
        schema_cache[scid] = schema
    # We depart from the JSON Schema specifications by disallowing
    # additional properties by default.
    # See `<https://bugzilla.wikimedia.org/show_bug.cgi?id=44454>`_.
    schema.setdefault('additionalProperties', False)
    if encapsulate:
        capsule = get_schema(CAPSULE_SCID)
        capsule['properties']['event'] = schema
        return capsule
    return schema


def http_get_schema(scid):
    """Retrieve schema via HTTP."""
    validate_scid(scid)
    url = SCHEMA_URL_FORMAT % scid
    try:
        schema = json.loads(http_get(url))
    except (ValueError, EnvironmentError) as ex:
        raise jsonschema.SchemaError('Schema fetch failure: %s' % ex)
    jsonschema.Draft3Validator.check_schema(schema)
    return schema


def validate_scid(scid):
    """Validates an SCID.
    :raises :exc:`jsonschema.ValidationError`: If SCID is invalid.
    """
    schema, revision = scid
    if not isinstance(revision, integer_types) or revision < 1:
        raise jsonschema.ValidationError('Invalid revision ID: %s' % revision)
    if not isinstance(schema, string_types) or not SCHEMA_RE.match(schema):
        raise jsonschema.ValidationError('Invalid schema name: %s' % schema)


def validate(capsule):
    """Validates an encapsulated event.
    :raises :exc:`jsonschema.ValidationError`: If event is invalid.
    """
    try:
        scid = capsule['schema'], capsule['revision']
    except KeyError as ex:
        # If `schema` or `revision` keys are missing, a KeyError
        # exception will be raised. We re-raise it as a
        # :exc:`ValidationError` to provide a simpler API for callers.
        raise jsonschema.ValidationError('Missing key: %s' % ex)
    schema = get_schema(scid, encapsulate=True)
    jsonschema.Draft3Validator(schema).validate(capsule)
