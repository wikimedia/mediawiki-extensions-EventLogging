# -*- coding: utf8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains tests for :module:`eventlogging.jrm`.

"""
from __future__ import unicode_literals

import unittest

import sqlalchemy
import eventlogging

from .fixtures import *


class JrmTestCase(DatabaseTestMixin, unittest.TestCase):

    def test_lazy_table_creation(self):
        """If an attempt is made to store an event for which no table
        exists, the schema is automatically retrieved and a suitable
        table generated."""
        eventlogging.store_event(self.meta, self.event)
        self.assertIn('TestSchema_123', self.meta.tables)

    def test_column_names(self):
        """Generated tables contain columns for each relevant field."""
        t = eventlogging.create_table(self.meta, TEST_SCHEMA_SCID)

        # The columns we expect to see are..
        cols = set(eventlogging.flatten(self.event))    # all properties
        cols -= set(eventlogging.jrm.NO_DB_PROPERTIES)  # unless excluded
        cols |= {'id', 'uuid'}                          # plus 'id' & 'uuid'.

        self.assertSetEqual(set(t.columns.keys()), cols)

    def test_index_creation(self):
        """The ``timestamp`` column is indexed by default."""
        t = eventlogging.create_table(self.meta, TEST_SCHEMA_SCID)
        cols = {column.name for index in t.indexes for column in index.columns}
        self.assertIn('timestamp', cols)

    def test_flatten(self):
        """``flatten`` correctly collapses deeply nested maps."""
        flat = eventlogging.flatten(self.event)
        self.assertEqual(flat['event_nested_deeplyNested_pi'], 3.14159)

    def test_encoding(self):
        """Timestamps and unicode strings are correctly encoded."""
        eventlogging.store_event(self.meta, self.event)
        meta = sqlalchemy.MetaData(bind=self.meta.bind)
        meta.reflect()
        result = meta.tables['TestSchema_123'].select().execute()
        row = result.fetchone()
        self.assertEqual(row['event_value'], '☆ 彡')
        self.assertEqual(row['timestamp'], '20130121101034')
        self.assertEqual(row['uuid'], 'babb66f34a0a5de3be0c6513088be33e')
