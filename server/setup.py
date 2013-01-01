"""
This module contains scripts for processing streams of events generated
by EventLogging_, a MediaWiki extension for logging structured data from
client-side code.

.. _EventLogging: http://www.mediawiki.org/wiki/Extension:EventLogging
"""
from setuptools import setup


setup(
    name='eventlogging',
    version='0.4',
    license='GPL',
    author='Ori Livneh',
    author_email='ori@wikimedia.org',
    url='https://www.mediawiki.org/wiki/Extension:EventLogging',
    description='Server-side component of EventLogging MediaWiki extension.',
    long_description=__doc__,
	classifiers=(
		'Development Status :: 4 - Beta',
		'License :: OSI Approved :: GNU General Public License v2 or later (GPLv2+)',
		'Programming Language :: JavaScript',
		'Programming Language :: PHP',
		'Programming Language :: Python :: 2.7',
		'Programming Language :: Python :: 3.3',
		'Topic :: Database',
		'Topic :: Scientific/Engineering :: Interface Engine/Protocol Translator',
		'Topic :: Software Development :: Object Brokering',
	),
    packages=(
        'eventlogging',
    ),
    scripts=(
        'bin/json2sql',
        'bin/log2json',
        'bin/udp2zmq',
        'bin/zmq2log',
		'bin/seqmon',
    ),
	zip_safe=False,
    install_requires=(
        "jsonschema>=0.7",
        "pyzmq>=2.1",
        "sqlalchemy>=0.7.9",
    ),
)
