# -*- coding: utf-8 -*-
"""
  eventlogging.factory
  ~~~~~~~~~~~~~~~~~~~~

  This module implements a factory-like map of URI scheme handlers.

"""
import inspect

from .compat import items, parse_qsl, urlparse


__all__ = ('apply_safe', 'drive', 'get_reader', 'get_writer', 'handle',
           'reads', 'writes')

_writers = {}
_readers = {}


def apply_safe(f, kwargs):
    """Apply a function with only those arguments that it would accept."""
    # If the function takes a '**' arg, all keyword args are safe.
    # If it doesn't, we have to remove any arguments that are not
    # present in the function's signature.
    sig = inspect.getargspec(f)
    if sig.keywords is None:
        kwargs = {k: v for k, v in items(kwargs) if k in sig.args}
    if sig.defaults is not None:
        args = [kwargs.pop(k) for k in sig.args[:-len(sig.defaults)]]
    else:
        args = [kwargs.pop(k) for k in sig.args]
    return f(*args, **kwargs)


def handle(handlers, uri):
    """Use a URI to look up a handler and then invoke the handler with
    the parts and params of a URI as kwargs."""
    parts = urlparse(uri)
    handler = handlers[parts.scheme]
    kwargs = dict(parse_qsl(parts.query), uri=uri)
    for k in 'hostname', 'port', 'path':
        kwargs[k] = getattr(parts, k)
    return apply_safe(handler, kwargs)


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
    coroutine = handle(_writers, uri)
    next(coroutine)
    return coroutine


def get_reader(uri):
    """Given a reader URI (representing the address of an input stream),
    invoke and initialize a generator that will yield values from that
    stream."""
    iterator = handle(_readers, uri)
    return iterator


def drive(in_url, out_url):
    """Impel data from a reader into a writer."""
    reader = get_reader(in_url)
    writer = get_writer(out_url)
    for event in reader:
        writer.send(event)
