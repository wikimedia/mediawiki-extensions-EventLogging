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

import logging

import jsonschema

from .compat import json, urlopen


_schemas = {}
_url_format = ('http://meta.wikimedia.org/w/index.php?action=raw'
               '&title=Schema:%s&oldid=%d')

CAPSULE_SCID = ('EventCapsule', 5125187)


def get_schema(scid):
    """Get schema from memory or HTTP."""
    schema = _schemas.get(scid)
    if schema is None:
        schema = http_get_schema(scid)
        if schema is not None:
            _schemas[scid] = schema
    return schema


def http_get_schema(scid):
    """Retrieve schema via HTTP."""
    req = urlopen(_url_format % scid)
    content = req.read().decode('utf-8')
    try:
        schema = json.loads(content)
        if not isinstance(schema, dict):
            raise TypeError
    except (TypeError, ValueError) as ex:
        logging.exception('Failed to decode HTTP response: %s', content)
        return None
    return schema


capsule_schema = get_schema(CAPSULE_SCID)


def validate(capsule):
    """Validates an encapsulated event.
    :raises :exc:`jsonschema.ValidationError`: If event is invalid.
    """
    jsonschema.validate(capsule, capsule_schema)
    try:
        event_schema = get_schema((capsule['schema'], capsule['revision']))
        jsonschema.validate(capsule['event'], event_schema)
    except KeyError as ex:
        # If `schema`, `revision` or `event` keys are missing, a
        # KeyError exception will be raised. We re-raise it as a
        # :exc:`ValidationError` to provide a simpler API for callers.
        raise jsonschema.ValidationError('Missing key: %s' % ex.message)
