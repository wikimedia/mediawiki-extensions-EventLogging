# EventLogging Devserver

## eventgate-devserver

This is a backend that uses event intake service and schema git repositories for schemas in the same way that WMF does in production.

Just:

```
npm run eventgate-devserver
```

This will install and start an eventgate-wikimedia-dev server listening on
port 8192, writing events out to `./events.json`, as well as trace logging
them and pretty printing them via bunyan formatter.

EventGate config is in `devserver/eventgate.config.yaml`.
The default config uses remote schema repos at https://schema.wikimedia.org.
You can modify these to a local file path of your schema repository.
Set `output_path` to a file to have valid events be output to a file instead
of stdout.

If you are developing schemas, you'll want to clone a schema repository
(likely [schemas/event/secondary](https://gerrit.wikimedia.org/g/schemas/event/secondary/+/refs/heads/master)) and edit `devserver/eventgate.config.yaml`
and change `schema_base_uris` to include the path to your clone of that repository.

NOTE: If you run npm install, you may want to do it excluding
optionalDependencies. node-rdkafka is an optional dependency of
eventgate-wikimedia, and it can be a little cumbersome to build and install.
Run npm install like
```
npm install --no-optional
```


## Legacy eventlogging-devserver

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
