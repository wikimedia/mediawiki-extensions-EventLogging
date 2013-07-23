# -*- coding: utf8 -*-
"""
  eventlogging unit tests
  ~~~~~~~~~~~~~~~~~~~~~~~

  This module contains tests for :module:`eventlogging.compat`.

"""
from __future__ import unicode_literals

import multiprocessing
import unittest
import wsgiref.simple_server

from eventlogging.compat import http_get


class SingleServingHttpd(multiprocessing.Process):
    def __init__(self, resp):
        self.resp = resp.encode('utf-8')
        super(SingleServingHttpd, self).__init__()

    def run(self):
        def app(environ, start_response):
            start_response(str('200 OK'), [])
            return [self.resp]
        httpd = wsgiref.simple_server.make_server('127.0.0.1', 44080, app)
        httpd.handle_request()


class HttpGetTestCase(unittest.TestCase):
    """Test cases for ``http_get``."""
    def test_http_get(self):
        """``http_get`` can pull content via HTTP."""
        server = SingleServingHttpd('secret')
        server.start()
        response = http_get('http://127.0.0.1:44080')
        self.assertEquals(response, 'secret')
