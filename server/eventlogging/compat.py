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

if sys.version_info[0] >= 3:
    items = operator.methodcaller('items')
    from urllib.parse import parse_qsl
    from urllib.request import urlopen
else:
    items = operator.methodcaller('iteritems')
    from urlparse import parse_qsl
    from urllib2 import urlopen
