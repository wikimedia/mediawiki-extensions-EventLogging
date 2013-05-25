# -*- coding: utf-8 -*-
"""
  eventlogging.base
  ~~~~~~~~~~~~~~~~~

  This module implements a factory-like map of URI scheme handlers.

"""
from .util import get_uri_scheme, get_uri_params

__all__ = ('writes', 'reads', 'get_writer', 'get_reader')


_writers = {}
_readers = {}


def writes(*schemes):
    def decorator(f):
        _writers.update((scheme, f) for scheme in schemes)
        return f
    return decorator


def reads(*schemes):
    def decorator(f):
        _readers.update((scheme, f) for scheme in schemes)
        return f
    return decorator


def get_writer(uri):
    writer = _writers[get_uri_scheme(uri)]
    coroutine = writer(uri, **get_uri_params(uri))
    next(coroutine)
    return coroutine


def get_reader(uri):
    reader = _readers[get_uri_scheme(uri)]
    return reader(uri, **get_uri_params(uri))
