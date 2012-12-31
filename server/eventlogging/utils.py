# -*- coding: utf-8 -*-
"""
  EventLogging
  ~~~~~~~~~~~~

  This module provides various helper functions.

  :copyright: (c) 2012 by Ori Livneh
  :license: GNU General Public Licence 2.0 or later

"""
from __future__ import unicode_literals

import calendar
import time
import hashlib
import os


# Salt value for hashing IPs. Because this value is generated at
# runtime, IPs cannot be compared across restarts, but it spares us
# having to secure a config file.
_salt = os.urandom(16)


def ncsa_to_epoch(ncsa_ts):
    """Converts a timestamp in NCSA format to seconds since epoch."""
    return calendar.timegm(time.strptime(ncsa_ts, '%Y-%m-%dT%H:%M:%S'))


def hash_value(strval):
    """Produce a salted SHA1 hash of any string value."""
    hval = hashlib.sha1(strval)
    hval.update(_salt)
    return hval.hexdigest()
