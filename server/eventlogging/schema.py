# -*- coding: utf-8 -*-
"""
  eventlogging.schema
  ~~~~~~~~~~~~~~~~~~~

  This module implements schema retrieval and validation. Schemas are
  retrieved via HTTP and then cached in-memory. Validation uses
  :module:`jsonschema`.

"""
from __future__ import unicode_literals

import logging

import jsonschema

from .compat import json, urlopen


_schemas = {}
_url_format = 'http://meta.wikimedia.org/w/index.php?action=raw&oldid=%d'

META_REV_ID = 5121646


def get_schema(rev_id):
    """Get schema from memory or HTTP."""
    schema = _schemas.get(rev_id)
    if schema is None:
        schema = http_get_schema(rev_id)
        if schema is not None:
            _schemas[rev_id] = schema
    return schema


def http_get_schema(rev_id):
    """Retrieve schema via HTTP."""
    req = urlopen(_url_format % rev_id)
    content = req.read().decode('utf8')
    try:
        schema = json.loads(content)
    except ValueError:
        logging.exception('Failed to decode HTTP response: %s', content)
        return None
    return schema


def validate(capsule):
    """Validates an encapsulated event."""
    meta_schema = get_schema(META_REV_ID)
    jsonschema.validate(capsule, meta_schema)
    event_schema = get_schema(capsule['revision'])
    jsonschema.validate(capsule['event'], event_schema)
