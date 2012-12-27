#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
  zmq2log.py
  ----------
  Log a ZeroMQ PUB stream.

  usage: zmq2log.py [-h] [--topic TOPIC] [--sid SID] publisher destfile

  Log a ZeroMQ PUB stream.

  positional arguments:
    publisher      publisher URI
    destfile       write log to this file

  optional arguments:
    -h, --help     show help message and exit
    --topic TOPIC  subscribe to topic (default: "")
    --sid SID      set socket identity (default: host name)

"""
import argparse
import logging
import logging.handlers
import socket

import zmq


hostname = socket.gethostname()


#
# Parse command-line args
#

parser = argparse.ArgumentParser(description='Log a ZeroMQ PUB stream.')
parser.add_argument('publisher', help='publisher URI')
parser.add_argument('destfile', help='write log to this file')
parser.add_argument('--topic', default='',
                    help='subscribe to topic (default: "")')
parser.add_argument('--sid', default=hostname,
                    help='set socket identity (default: host name)')

args = parser.parse_args()


#
# Configure logging
#

# Configure logging to file:
logfile_handler = logging.handlers.WatchedFileHandler(filename=args.destfile)
logfile_handler.setLevel(logging.INFO)

# Configure logging to stderr:
console_handler = logging.StreamHandler()
console_handler.setFormatter(logging.Formatter('%(asctime)s\t%(message)s'))
console_handler.setLevel(logging.DEBUG)  # Don't pollute log files with status

log = logging.getLogger(__name__)
log.setLevel(logging.DEBUG)
log.addHandler(logfile_handler)
log.addHandler(console_handler)
log.debug('Started. Logging to %s.' % args.destfile)


#
# Configure ZeroMQ Subscriber
#

context = zmq.Context.instance()
socket = context.socket(zmq.SUB)
socket.connect(args.publisher)
socket.setsockopt(zmq.IDENTITY, args.sid.encode('utf8'))
socket.setsockopt(zmq.SUBSCRIBE, args.topic.encode('utf8'))
log.debug('Connected to %s/%s' % (args.publisher, args.topic))


while 1:
    log.info(socket.recv().rstrip())
