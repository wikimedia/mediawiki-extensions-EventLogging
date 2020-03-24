EventLogging Devserver
============

This is a basic EventLogging server that only handles HTTP ingestion and validation.

## Install

You need Python 3.3 or later installed. Then run:

    $ python3 -m venv .env
    $ source .env/bin/activate
    $ pip install .
    $ ./bin/eventlogging-devserver --verbose

## Run tests

    $ source .env/bin/activate
    $ python3 -m unittest discover

## See also

* <https://www.mediawiki.org/wiki/Extension:EventLogging>
