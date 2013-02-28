# -*- coding: utf8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains tests for :module:`eventlogging.jrm`.

"""
from __future__ import unicode_literals

import datetime
import unittest

import eventlogging
import sqlalchemy

from .fixtures import DatabaseTestMixin, TEST_SCHEMA_SCID


class JrmTestCase(DatabaseTestMixin, unittest.TestCase):
    """Test case for :module:`eventlogging.jrm`."""

    def test_lazy_table_creation(self):
        """If an attempt is made to store an event for which no table
        exists, the schema is automatically retrieved and a suitable
        table generated."""
        eventlogging.store_event(self.meta, self.event)
        self.assertIn('TestSchema_123', self.meta.tables)

    def test_column_names(self):
        """Generated tables contain columns for each relevant field."""
        t = eventlogging.jrm.declare_table(self.meta, TEST_SCHEMA_SCID)

        # The columns we expect to see are..
        cols = set(eventlogging.jrm.flatten(self.event))  # all properties
        cols -= set(eventlogging.jrm.NO_DB_PROPERTIES)    # unless excluded
        cols |= {'id', 'uuid'}                            # plus 'id' & 'uuid'

        self.assertSetEqual(set(t.columns.keys()), cols)

    def test_index_creation(self):
        """The ``timestamp`` column is indexed by default."""
        t = eventlogging.jrm.declare_table(self.meta, TEST_SCHEMA_SCID)
        cols = {column.name for index in t.indexes for column in index.columns}
        self.assertIn('timestamp', cols)

    def test_flatten(self):
        """``flatten`` correctly collapses deeply nested maps."""
        flat = eventlogging.jrm.flatten(self.event)
        self.assertEqual(flat['event_nested_deeplyNested_pi'], 3.14159)

    def test_encoding(self):
        """Timestamps and unicode strings are correctly encoded."""
        eventlogging.jrm.store_event(self.meta, self.event)
        table = eventlogging.jrm.get_table(self.meta, TEST_SCHEMA_SCID)
        row = table.select().execute().fetchone()
        self.assertEqual(row['event_value'], '☆ 彡')
        self.assertEqual(row['uuid'], 'babb66f34a0a5de3be0c6513088be33e')
        self.assertEqual(
            row['timestamp'],
            datetime.datetime(2013, 1, 21, 18, 10, 34)
        )

    def test_reflection(self):
        """Tables which exist in the database but not in the MetaData cache are
        correctly reflected."""
        eventlogging.store_event(self.meta, self.event)

        # Tell Python to forget everything it knows about this database
        # by purging ``MetaData``. The actual data in the database is
        # not altered by this operation.
        del self.meta
        self.meta = sqlalchemy.MetaData(bind=self.engine)

        # Although ``TestSchema_123`` exists in the database, SQLAlchemy
        # is not yet aware of its existence:
        self.assertNotIn('TestSchema_123', self.meta.tables)

        # The ``checkfirst`` arg to :func:`sqlalchemy.Table.create`
        # will ensure that we don't attempt to CREATE TABLE on the
        # already-existing table:
        eventlogging.store_event(self.meta, self.event)
        self.assertIn('TestSchema_123', self.meta.tables)
