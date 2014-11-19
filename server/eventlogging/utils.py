# -*- coding: utf-8 -*-
"""
  eventlogging.utils
  ~~~~~~~~~~~~~~~~~~

  This module contains generic routines that aren't associated with
  a particular function.

"""
from __future__ import unicode_literals

import threading


__all__ = ('PeriodicThread',)


class PeriodicThread(threading.Thread):
    """Represents a threaded job that runs repeatedly at regular intervals."""

    def __init__(self, interval, *args, **kwargs):
        self.interval = interval
        self.ready = threading.Event()
        super(PeriodicThread, self).__init__(*args, **kwargs)

    def run(self):
        while 1:
            self.ready.clear()
            self.ready.wait(self.interval)
            self._Thread__target(*self._Thread__args, **self._Thread__kwargs)
