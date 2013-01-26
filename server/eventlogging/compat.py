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

import operator
import sys

from zmq.utils import jsonapi as json


__all__ = ('items', 'json', 'urlopen', 'unquote_plus')

PY3 = sys.version_info[0] == 3

if PY3:
    items = operator.methodcaller('items')
    from urllib.parse import unquote_plus
    from urllib.request import urlopen
else:
    items = operator.methodcaller('iteritems')
    from urllib2 import urlopen
    from urllib import unquote_plus
