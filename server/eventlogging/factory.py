# -*- coding: utf-8 -*-
"""
  eventlogging.factory
  ~~~~~~~~~~~~~~~~~~~~

  This module implements a factory-like map of URI scheme handlers.

"""
import inspect

from .compat import items, parse_qsl


__all__ = ('get_reader', 'get_writer', 'reads', 'writes', 'drive')

_writers = {}
_readers = {}
_mappers = {}


def deparam(uri):
    """Parse query string from URI into a dict."""
    qs = uri.rsplit('?', 1)[-1]
    return dict(parse_qsl(qs))


def call(f, kwargs):
    """Inspect a function's signature and call it with the keyword arguments in
    `kwargs` that it accepts. Other arguments are discarded."""
    sig = inspect.getargspec(f)
    if sig.keywords is None:
        kwargs = {k: v for k, v in items(kwargs) if k in sig.args}
    return f(**kwargs)


def mapper(f):
    """Decorator that registers a function as a mapper."""
    _mappers[f.__name__] = f
    return f


def writes(*schemes):
    """Decorator that takes URI schemes as parameters and registers the
    decorated function as an event writer for those schemes."""
    def decorator(f):
        _writers.update((scheme, f) for scheme in schemes)
        return f
    return decorator


def reads(*schemes):
    """Decorator that takes URI schemes as parameters and registers the
    decorated function as an event reader for those schemes."""
    def decorator(f):
        _readers.update((scheme, f) for scheme in schemes)
        return f
    return decorator


def get_writer(uri):
    """Given a writer URI (representing, for example, a database
    connection), invoke and initialize the appropriate handler."""
    uri_scheme = uri.split('://', 1)[0]
    writer = _writers[uri_scheme]
    params = dict(deparam(uri), uri=uri)
    coroutine = call(writer, params)
    next(coroutine)
    return coroutine


def get_reader(uri):
    """Given a reader URI (representing the address of an input stream),
    invoke and initialize a generator that will yield values from that
    stream."""
    uri_scheme = uri.split('://', 1)[0]
    reader = _readers[uri_scheme]
    params = dict(deparam(uri), uri=uri)
    mappers = params.pop('mappers', None)
    iterator = call(reader, params)
    if mappers is not None:
        mappers = [_mappers[mapper] for mapper in mappers.split(',')]
        for mapper in mappers:
            iterator = mapper(iterator)
    return iterator


def drive(in_url, out_url):
    """Impel data from a reader into a writer."""
    reader = get_reader(in_url)
    writer = get_writer(out_url)
    for event in reader:
        writer.send(event)
