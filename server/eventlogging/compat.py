# -*- coding: utf-8 -*-
"""
  eventlogging.compat
  ~~~~~~~~~~~~~~~~~~~

  The source code for EventLogging aims to be compatible with both
  Python 2 and 3 without requiring any translation to run on one or the
  other version. This module supports this goal by providing import
  paths and helper functions that wrap differences between Python 2 and
  Python 3.

"""
# flake8: noqa
# pylint: disable=E0611, F0401, E1101
from __future__ import unicode_literals

import functools
import hashlib
import operator
import sys
import uuid


try:
    import simplejson as json
except ImportError:
    import json


__all__ = ('items', 'json', 'unquote_plus', 'urlopen', 'uuid5')

PY3 = sys.version_info[0] == 3

if PY3:
    items = operator.methodcaller('items')
    from urllib.parse import unquote_to_bytes as unquote
    from urllib.request import urlopen
else:
    items = operator.methodcaller('iteritems')
    from urllib2 import urlopen
    from urllib import unquote


def unquote_plus(unicode):
    """Replace %xx escapes by their single-character equivalent."""
    unicode = unicode.replace('+', ' ')
    bytes = unicode.encode('utf-8')
    return unquote(bytes).decode('utf-8')


@functools.wraps(uuid.uuid5)
def uuid5(namespace, name):
    """Generate UUID5 for `name` in `namespace`."""
    # Python 2 expects `name` to be bytes; Python 3, unicode. This
    # variant expects unicode strings in both Python 2 and Python 3.
    hash = hashlib.sha1(namespace.bytes + name.encode('utf-8')).digest()
    return uuid.UUID(bytes=hash[:16], version=5)
