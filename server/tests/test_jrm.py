# -*- coding: utf8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains tests for :module:`eventlogging.jrm`.

"""
from __future__ import unicode_literals

import unittest

import eventlogging

from .fixtures import *


class JrmTestCase(DatabaseTestMixin, unittest.TestCase):

    def test_table_creation(self):
        """Tables are created as needed."""
        self.assertNotIn('TestSchema_123', self.meta.tables.keys())
        eventlogging.store_event(self.meta, self.event)
        self.assertIn('TestSchema_123', self.meta.tables.keys())
