# -*- coding: utf8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains tests for :module:`eventlogging.util`.

"""
from __future__ import unicode_literals

import unittest

from eventlogging.util import *


class UtilTestCase(unittest.TestCase):
    uri = 'tcp://127.0.0.1/?key=value'

    def test_get_uri_scheme(self):
        self.assertEqual(get_uri_scheme(self.uri), 'tcp')

    def test_get_url_params(self):
        self.assertEqual(get_uri_params(self.uri), {'key': 'value'})

    def test_strip_qs(self):
        self.assertEqual(strip_qs(self.uri), 'tcp://127.0.0.1/')
