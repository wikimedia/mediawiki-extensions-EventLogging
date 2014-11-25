# -*- coding: utf-8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains tests for :module:`eventlogging.utils`.

"""
from __future__ import unicode_literals

import unittest

import eventlogging


class UriDeleteQueryItemTestCase(unittest.TestCase):
    """Test cases for ``uri_delete_query_item``."""

    def test_uri_delete_query_item(self):
        uri = 'http://www.com?aa=aa&bb=bb&cc=cc'
        test_data = (
            ('aa', 'http://www.com?bb=bb&cc=cc'),
            ('bb', 'http://www.com?aa=aa&cc=cc'),
            ('cc', 'http://www.com?aa=aa&bb=bb'),
        )
        for key, expected_uri in test_data:
            self.assertEqual(
                eventlogging.uri_delete_query_item(uri, key),
                expected_uri
            )
