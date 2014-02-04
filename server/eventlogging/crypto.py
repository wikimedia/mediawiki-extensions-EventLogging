# -*- coding: utf-8 -*-
"""
  eventlogging.crypto
  ~~~~~~~~~~~~~~~~~~~

  This module implements ephemeral key-hashing, used by EventLogging to
  anonymize IP addresses.

"""
from __future__ import unicode_literals

import hashlib
import hmac
import os
import time


__all__ = ('keyhasher', 'rotating_key')


def rotating_key(size, period):
    """Produce a random key of `size` bytes and yield it repeatedly until
    `period` seconds expire, at which point a new key is produced.

    :param size: Byte length of key.
    :param period: Key lifetime in seconds.
    """
    while 1:
        key = os.urandom(size)
        created = time.time()
        while (time.time() - created) <= period:
            yield key


def keyhasher(keys, digestmod=hashlib.sha1):
    """Returns an HMAC function that acquires keys from an iterator."""
    keys_iter = iter(keys)

    def hash_func(msg):
        """HMAC function bound to a key iterator and hash function."""
        code = hmac.new(next(keys_iter), msg.encode('utf-8'), digestmod)
        return code.hexdigest()

    return hash_func
