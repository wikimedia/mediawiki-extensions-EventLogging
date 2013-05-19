# -*- coding: utf-8 -*-
"""
  eventlogging.parse
  ~~~~~~~~~~~~~~~~~~

  This module provides a scanf-like parser for raw log lines.

  The format specifiers hew closely to those accepted by varnishncsa.
  See the `varnishncsa documentation <https://www.varnish-cache.org
  /docs/trunk/reference/varnishncsa.html>`_ for details.

  Field specifiers
  ================

  +--------+-----------------------------+
  | Symbol | Field                       |
  +========+=============================+
  |   %h   | Client IP                   |
  +--------+-----------------------------+
  |   %j   | JSON object                 |
  +--------+-----------------------------+
  |   %l   | Hostname of origin          |
  +--------+-----------------------------+
  |   %n   | Sequence ID                 |
  +--------+-----------------------------+
  |   %q   | Query-string-encoded JSON   |
  +--------+-----------------------------+
  |   %t   | Timestamp in NCSA format.   |
  +--------+-----------------------------+

"""
from __future__ import division, unicode_literals

import calendar
import hashlib
import os
import re
import time
import uuid

from .compat import json, unquote_plus, uuid5


__all__ = ('LogParser', 'ncsa_to_epoch', 'ncsa_utcnow', 'capsule_uuid')

#: Salt value for hashing IPs. Because this value is generated at
#: runtime, IPs cannot be compared across restarts. This limitation is
#: tolerated because it helps underscore the field's unsuitability for
#: analytic purposes. Client IP is logged solely for detecting and
#: grouping spam coming from a single origin so that it can be filtered
#: out of the logs.
salt = os.urandom(16)

#: Format string (as would be passed to `strftime`) for timestamps in
#: NCSA Common Log Format.
NCSA_FORMAT = '%Y-%m-%dT%H:%M:%S'

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


def ncsa_to_epoch(ncsa_ts):
    """Converts an NCSA Common Log Format timestamp to an integer
    timestamp representing the number of seconds since epoch UTC.

    :param ncsa_ts: Timestamp in NCSA format.
    """
    return calendar.timegm(time.strptime(ncsa_ts, NCSA_FORMAT))


def ncsa_utcnow():
    """Gets the current UTC date and time in NCSA Common Log Format"""
    return time.strftime(NCSA_FORMAT, time.gmtime())


def hash_value(val):
    """Produces a salted SHA1 hash of any string value.
    :param val: String to hash.
    """
    hash = hashlib.sha1(val.encode('utf-8') + salt)
    return hash.hexdigest()


def decode_qson(qson):
    """Decodes a QSON (query-string-encoded JSON) object.
    :param qs: Query string.
    """
    return json.loads(unquote_plus(qson.strip('?;')))


#: A mapping of format specifiers to a tuple of (regexp, caster).
format_specifiers = {
    '%h': (r'(?P<clientIp>\S+)', hash_value),
    '%j': (r'(?P<capsule>\S+)', json.loads),
    '%l': (r'(?P<recvFrom>\S+)', str),
    '%n': (r'(?P<seqId>\d+)', int),
    '%q': (r'(?P<capsule>\?\S+)', decode_qson),
    '%t': (r'(?P<timestamp>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})',
           ncsa_to_epoch),
}


class LogParser(object):
    """Parses raw varnish/MediaWiki log lines into encapsulated events."""

    def __init__(self, format):
        """Constructor.

        :param format: Format string.
        """
        self.format = format

        #: Field casters, ordered by the relevant field's position in
        #: format string.
        self.casters = []

        #: Compiled regexp.
        self.re = re.compile(re.sub(r'(?<!%)%[hjlnqt]', self._repl, format))

    def _repl(self, spec):
        """Replace a format specifier with its expanded regexp matcher
        and append its caster to the list. Called by :func:`re.sub`.
        """
        matcher, caster = format_specifiers[spec.group()]
        self.casters.append(caster)
        return matcher

    def parse(self, line):
        """Parse a log line into a map of field names / values."""
        match = self.re.match(line)
        if match is None:
            raise ValueError(self.re, line)
        keys = sorted(match.groupdict(), key=match.start)
        event = {k: f(match.group(k)) for f, k in zip(self.casters, keys)}
        event.update(event.pop('capsule'))
        event['uuid'] = capsule_uuid(event)
        return event

    def __repr__(self):
        return '<LogParser(\'%s\')>' % self.format
