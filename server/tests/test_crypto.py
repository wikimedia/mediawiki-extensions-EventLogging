# -*- coding: utf8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains tests for :module:`eventlogging.crypto`.

"""
from __future__ import unicode_literals

import time
import unittest

import eventlogging


class KeyHasherTestCase(unittest.TestCase):
    """Test case for :func:`eventlogging.keyhasher`."""

    def test_hash_function(self):
        """``keyhasher`` produces HMAC SHA1 using key iterator for keys"""
        hash_func = eventlogging.keyhasher((b'key1', b'key2'))
        self.assertEqual(
            hash_func('message1'),
            'e45a01bfebb0d5596564cc7b712b2d570041a839'
        )
        self.assertEqual(
            hash_func('message2'),
            'c8ec32b32d5bd7dc5a6a0b203f7f220bb641f52c'
        )

    def test_keys_depleted(self):
        """``keyhasher`` raises StopIteration if key iterator is depleted."""
        hash_func = eventlogging.keyhasher(())
        with self.assertRaises(StopIteration):
            hash_func('message')


class RotatingKeyTestCase(unittest.TestCase):
    """Test case for :func:`eventlogging.rotating_key`."""

    def test_key_repeats(self):
        """``rotating_key`` yields the same key until that key expires."""
        key_iter = eventlogging.rotating_key(size=64, period=60)
        self.assertEqual(next(key_iter), next(key_iter))

    def test_key_expires(self):
        """``rotating_key`` produces a new key when the old key expires."""
        key_iter = eventlogging.rotating_key(size=64, period=0.001)
        key1 = next(key_iter)
        time.sleep(0.01)
        key2 = next(key_iter)
        self.assertNotEqual(key1, key2)
