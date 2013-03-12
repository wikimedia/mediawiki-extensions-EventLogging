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

import uuid

import jsonschema

from .compat import json, urlopen, uuid5

__all__ = ('CAPSULE_SCID', 'capsule_uuid', 'get_schema', 'SCHEMA_URL_FORMAT',
           'validate')


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


#: Formats event capsule objects into URLs using the combination of
#: origin hostname, sequence ID, and timestamp. This combination is
#: guaranteed to be unique. Example::
#:
#:   event://vanadium.eqiad.wmnet/?seqId=438763&timestamp=1359702955
#:
EVENTLOGGING_URL_FORMAT = (
    'event://%(recvFrom)s/?seqId=%(seqId)s&timestamp=%(timestamp).10s')


def capsule_uuid(capsule):
    """Generate a UUID for a capsule object.

    Gets a unique URI for the capsule using `EVENTLOGGING_URL_FORMAT`
    and uses it to generate a UUID5 in the URL namespace.

    ..seealso:: `RFC 4122 <http://www.ietf.org/rfc/rfc4122.txt>`_.

    :param capsule: A capsule object (or any dictionary that defines
      `recvFrom`, `seqId`, and `timestamp`).

    """
    id = uuid5(uuid.NAMESPACE_URL, EVENTLOGGING_URL_FORMAT % capsule)
    return '%032x' % id.int


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
    jsonschema.validate(capsule, schema)
