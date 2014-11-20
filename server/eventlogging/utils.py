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
            if self.ready.wait(self.interval):
                # If the internal flag of `self.ready` was set, we were
                # interrupted mid-nap to run immediately. But before we
                # do, we reset the flag.
                self.ready.clear()
            self._Thread__target(*self._Thread__args, **self._Thread__kwargs)
