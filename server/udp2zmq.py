#!/usr/bin/env python
# -*- coding: utf8 -*-
"""
  ZeroMQ UDP => PUB Device

  UDP -> ZeroMQ socket forwarding. Reads line-oriented input from UDP socket
  and writes it to a ZeroMQ TCP PUB socket bound to the same port number.

  Because ZeroMQ is message-oriented, we cannot simply use recv_into to read
  bytes from the UDP socket into the ZMQ socket. We use socket.makefile() to
  facilitate reading and writing whole lines.

  usage: udp2zmq.py [-h] port

  Author: Ori Livneh
  License: GPLv2 or later
"""
import argparse
import logging
import socket
import sys
import zmq


# Parse command-line arguments
parser = argparse.ArgumentParser(description='ZeroMQ UDP => PUB Device')
parser.add_argument('port', type=int, help='Port to forward')
args = parser.parse_args()


# Configure logging
logging.basicConfig(
    stream=sys.stderr,
    level=logging.DEBUG,
    format='%(asctime)s %(message)s'
)

# Bind output socket
ctx = zmq.Context()
sock_out = ctx.socket(zmq.PUB)
sock_out.bind('tcp://*:%d' % args.port)

# Bind input socket
sock_in = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
sock_in.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
sock_in.bind(('0.0.0.0', args.port))


try:
    f = sock_in.makefile('r', 1)
    logging.info('Forwarding udp:%d => tcp:%d...' % (args.port, args.port))
    for msg in f:
        sock_out.send(msg)
finally:
    try:
        f.close()
        sock_in.shutdown()
        sock_in.close()
        sock_out.close()
    except:
        pass
