EventLogging
============

This module contains scripts for processing streams of events generated
by EventLogging_, a MediaWiki extension for logging structured data from
client-side code.

To install dependencies in Ubuntu / Debian, simply run::

    $ sudo apt-get install -y python-coverage python-mysqldb python-nose
        python-pip python-sqlalchemy python-zmq

.. _EventLogging: http://www.mediawiki.org/wiki/Extension:EventLogging

The file ``setup.py`` lists the numerous dependencies under
``install_requires``. Running ``setup.py install`` configures the
server/eventlogging library and adds the programs in server/bin to your
path.
