#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
  seqmon.py
  ---------
  Monitor sequence IDs on a stream of varnishncsa udp output for gaps.

  usage: seqmon.py [-h] stream destfile

  Monitor udp2log sequence IDs.

  positional arguments:
    stream      log file or stream URI
    destfile    write log to this file

  optional arguments:
    -h, --help  show this help message and exit

  :copyright: (c) 2012 by Ori Livneh <ori@wikimedia.org>
  :license: GNU General Public Licence 2.0 or later

"""
import argparse
import collections
import logging
import logging.handlers

from eventlogging.stream import zmq_subscribe, tail_follow


#
# Parse command-line args
#

parser = argparse.ArgumentParser(description='Monitor udp2log sequence IDs.')
parser.add_argument('stream', help='log file or stream URI')
parser.add_argument('destfile', help='write log to this file')
args = parser.parse_args()


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

lost = collections.defaultdict(int)
seqs = {}


if args.stream.startswith('tcp://'):
    stream = zmq_subscribe(args.stream)
else:
    stream = tail_follow(args.stream)


for line in stream:
    try:
        host, seq = line.split(' ', 3)[1:3]
        seq = int(seq)
    except (ValueError, IndexError):
        log.exception('Unable to parse log line: %s', line)
        continue

    last = seqs.get(host)
    seqs[host] = seq

    if last is not None and last < (seq - 1):
        skipped = seq - last - 1
        log.error('%s: %d -> %d (skipped: %d)', host, last, seq, skipped)
        lost[host] += skipped
