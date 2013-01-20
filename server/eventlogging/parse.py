# -*- coding: utf-8 -*-
"""
  EventLogging
  ~~~~~~~~~~~~

  This module provides a scanf-like parser for raw log lines.

  The format specifiers hew closely to those accepted by varnishncsa.
  See <https://www.varnish-cache.org/docs/trunk/reference/varnishncsa.html>.

  Field specifiers:

     %h         Client IP
     %j         JSON object
     %l         Hostname of origin
     %n         Sequence ID
     %q         Query-string-encoded JSON
     %t         Timestamp in NCSA format.

  :copyright: (c) 2012 by Ori Livneh
  :license: GNU General Public Licence 2.0 or later

"""
from __future__ import unicode_literals

import calendar
import hashlib
import os
import re
import time

from .compat import json, unquote_plus


__all__ = ('LogParser', 'hash_value')

#: Salt value for hashing IPs. Because this value is generated at
#: runtime, IPs cannot be compared across restarts, but it spares
#: us having to secure a config file.
_salt = os.urandom(16)


def ncsa_to_epoch(ncsa_ts):
    """
    Converts a timestamp in NCSA format to seconds since epoch.
    :param ncsa_ts: Timestamp in NCSA format.
    """
    return calendar.timegm(time.strptime(ncsa_ts, '%Y-%m-%dT%H:%M:%S'))


def hash_value(val):
    """
    Produce a salted SHA1 hash of any string value.
    :param val: String to hash.
    """
    hval = hashlib.sha1(val.encode('utf8'))
    hval.update(_salt)
    return hval.hexdigest()


def decode_qs(qs):
    """
    Decode a query-string-encoded JSON object.
    :param qs: Query string.
    """
    return json.loads(unquote_plus(qs.strip('?;')))


#: A mapping of format specifiers to a tuple of (regexp, caster).
format_specifiers = {
    '%h': (r'(?P<clientIp>\S+)', hash_value),
    '%j': (r'(?P<event>\S+)', json.loads),
    '%l': (r'(?P<origin>\S+)', str),
    '%n': (r'(?P<seqId>\d+)', int),
    '%q': (r'(?P<event>\?\S+)', decode_qs),
    '%t': (r'(?P<timestamp>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})',
           ncsa_to_epoch),
}


class LogParser(object):
    """
    Parses raw varnish/MediaWiki log lines into encapsulated events.
    """

    def __init__(self, fmt):
        """
        Constructor.
        :param fmt: Format string.
        """
        #: Field casters, ordered by the relevant field's position in
        #: format string.
        self.casters = []
        #: Compiled regexp.
        self.re = re.sub(r'(?<!%)%[hjlnqt]', self._repl, fmt)

    def _repl(self, spec):
        """
        Replace a format specifier with its expanded regexp matcher and
        append its caster to the list. Called by :func:`re.sub`.
        """
        matcher, caster = format_specifiers[spec.group()]
        self.casters.append(caster)
        return matcher

    def parse(self, line):
        """
        Parse a log line into a map of field names to field values.
        :param line: Log line to parse.
        """
        match = re.match(self.re, line)
        keys = sorted(match.groupdict(), key=match.start)
        event = {k: f(match.group(k)) for f, k in zip(self.casters, keys)}
        event.update(event.pop('event'))
        return event
