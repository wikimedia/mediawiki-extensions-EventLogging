# -*- coding: utf-8 -*-
"""
  eventlogging.utils
  ~~~~~~~~~~~~~~~~~~

  This module contains generic routines that aren't associated with
  a particular function.

"""
from __future__ import unicode_literals

import re
import threading

from .compat import monotonic_clock


__all__ = ('PeriodicThread', 'uri_delete_query_item')


class PeriodicThread(threading.Thread):
    """Represents a threaded job that runs repeatedly at a regular interval."""

    def __init__(self, interval, *args, **kwargs):
        self.interval = interval
        self.ready = threading.Event()
        self.stopping = threading.Event()
        super(PeriodicThread, self).__init__(*args, **kwargs)

    def run(self):
        while not self.stopping.is_set():
            # Run the target function. Check the clock before
            # and after to determine how long it took to run.
            time_start = monotonic_clock()
            self._Thread__target(*self._Thread__args, **self._Thread__kwargs)
            time_stop = monotonic_clock()

            run_duration = time_stop - time_start

            # Subtract the time it took the target function to run
            # from the desired run interval. The result is how long
            # we have to sleep before the next run.
            time_to_next_run = self.interval - run_duration

            if self.ready.wait(time_to_next_run):
                # If the internal flag of `self.ready` was set, we were
                # interrupted mid-nap to run immediately. But before we
                # do, we reset the flag.
                self.ready.clear()

    def stop(self):
        """Graceful stop: stop once the current iteration is complete."""
        self.stopping.set()


def uri_delete_query_item(uri, key):
    """Delete a key=value pair (specified by key) from a URI's query string."""
    def repl(match):
        separator, trailing_ampersand = match.groups()
        return separator if trailing_ampersand else ''
    return re.sub('([?&])%s=[^&]*(&?)' % re.escape(key), repl, uri)
