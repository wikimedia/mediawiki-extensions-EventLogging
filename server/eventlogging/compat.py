# -*- coding: utf-8 -*-
"""
  EventLogging
  ~~~~~~~~~~~~

  This module abstracts 2to3 compatibility.

  :copyright: (c) 2012 by Ori Livneh
  :license: GNU General Public Licence 2.0 or later

"""
__all__ = ('items', 'json', 'parse_qsl', 'urlopen')

import operator
import sys

from zmq.utils import jsonapi as json

PY3 = sys.version_info[0] == 3

if PY3:
    items = operator.methodcaller('items')
    from urllib.parse import parse_qsl
    from urllib.request import urlopen
else:
    items = operator.methodcaller('iteritems')
    from urlparse import parse_qsl
    from urllib2 import urlopen


def iter_socket(sock, encoding='utf8', errors='replace'):
    """2/3 compatible line-based iterator on sockets."""
    if PY3:
        f = sock.makefile(buffering=1, encoding=encoding, errors=errors)
        try:
            for line in f:
                yield line
        finally:
            f.close()
    else:
        f = sock.makefile()
        try:
            for line in f:
                yield line.decode(encoding, errors)
        finally:
            f.close()
