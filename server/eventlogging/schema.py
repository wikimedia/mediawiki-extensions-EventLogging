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

import jsonschema

from .compat import json, urlopen

__all__ = ('CAPSULE_SCID', 'get_schema', 'SCHEMA_URL_FORMAT', 'validate')


#: URL of index.php on the schema wiki (same as
#: '$wgEventLoggingSchemaIndexUri').
SCHEMA_WIKI_INDEX_PHP = 'http://meta.wikimedia.org/w/index.php'

#: Template for schema article URLs. Interpolates SCIDs.
SCHEMA_URL_FORMAT = SCHEMA_WIKI_INDEX_PHP + '?title=Schema:%s&oldid=%s'

#: Template for raw schema URLs. Interpolates SCIDs.
RAW_SCHEMA_URL_FORMAT = SCHEMA_URL_FORMAT + '&action=raw'

#: Schemas retrieved via HTTP are cached in this dictionary.
schema_cache = {}

#: SCID of the metadata object which wraps each event.
CAPSULE_SCID = ('EventCapsule', 5315751)


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
    url = RAW_SCHEMA_URL_FORMAT % scid
    try:
        content = urlopen(url).read().decode('utf-8')
        schema = json.loads(content)
    except (ValueError, EnvironmentError) as ex:
        raise jsonschema.SchemaError('Schema fetch failure: %s' % ex)
    jsonschema.Draft3Validator.check_schema(schema)
    return schema


def validate(capsule):
    """Validates an encapsulated event.
    :raises :exc:`jsonschema.ValidationError`: If event is invalid.
    """
    try:
        scid = capsule['schema'], capsule['revision']
    except KeyError as ex:
        # If `schema`, `revision` or `event` keys are missing, a
        # KeyError exception will be raised. We re-raise it as a
        # :exc:`ValidationError` to provide a simpler API for callers.
        raise jsonschema.ValidationError('Missing key: %s' % ex)
    schema = get_schema(scid, encapsulate=True)
    jsonschema.Draft3Validator.check_schema(schema)
    jsonschema.Draft3Validator(schema).validate(capsule)
