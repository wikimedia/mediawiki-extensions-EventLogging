# -*- coding: utf-8 -*-
"""
  eventlogging.util
  ~~~~~~~~~~~~~~~~~

  This module provides a set of discrete and generic helper functions.

"""
import re

from .compat import parse_qsl


__all__ = ('get_uri_scheme', 'get_uri_params', 'strip_qs')


def get_uri_scheme(uri):
    return uri.split('://', 1)[0]


def get_uri_params(uri):
    qs = uri.rsplit('?', 1)[-1]
    return dict(parse_qsl(qs))


def strip_qs(uri):
    return uri.rsplit('?', 1)[0]
