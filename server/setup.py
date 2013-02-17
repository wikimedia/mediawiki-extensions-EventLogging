"""
eventlogging
~~~~~~~~~~~~

This module contains scripts for processing streams of events generated
by `EventLogging`_, a MediaWiki extension for logging structured data.

.. _EventLogging: http://www.mediawiki.org/wiki/Extension:EventLogging

"""
try:
    from setuptools import setup
except ImportError:
    from distutils.core import setup

# Workaround for <http://bugs.python.org/issue15881#msg170215>:
import multiprocessing


setup(
    name='eventlogging',
    version='0.5',
    license='GPL',
    author='Ori Livneh',
    author_email='ori@wikimedia.org',
    url='https://www.mediawiki.org/wiki/Extension:EventLogging',
    description='Server-side component of EventLogging MediaWiki extension.',
    long_description=__doc__,
    classifiers=(
        'Development Status :: 4 - Beta',
        'License :: OSI Approved :: '
            'GNU General Public License v2 or later (GPLv2+)',
        'Programming Language :: JavaScript',
        'Programming Language :: PHP',
        'Programming Language :: Python :: 2.7',
        'Programming Language :: Python :: 3.3',
        'Topic :: Database',
        'Topic :: Scientific/Engineering :: '
            'Interface Engine/Protocol Translator',
        'Topic :: Software Development :: Object Brokering',
    ),
    packages=(
        'eventlogging',
    ),
    scripts=(
        'bin/eventlogging-devserver',
        'bin/json2sql',
        'bin/log2json',
        'bin/seqmon',
        'bin/sv-alerts',
        'bin/udp2zmq',
        'bin/zmq2log',
        'bin/zmux',
    ),
    zip_safe=False,
    test_suite='tests',
    install_requires=(
        "jsonschema>=0.7",
        "pygments>=1.5",
        "pyzmq>=2.1",
        "sqlalchemy>=0.7",
    ),
)
