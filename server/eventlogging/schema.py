# -*- coding: utf-8 -*-
"""
  EventLogging
  ~~~~~~~~~~~~

  This module implements schema retrieval.

  :copyright: (c) 2012 by Ori Livneh
  :license: GNU General Public Licence 2.0 or later

"""
from __future__ import unicode_literals

import logging

from .compat import json, urlopen


_schemas = {}
_url_format = 'http://meta.wikimedia.org/w/index.php?action=raw&oldid=%d'
_meta_schema_rev = 4891798


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
