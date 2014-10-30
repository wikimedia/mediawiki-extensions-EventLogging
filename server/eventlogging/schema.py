# -*- coding: utf-8 -*-
"""
  eventlogging.schema
  ~~~~~~~~~~~~~~~~~~~

  This module implements schema retrieval and validation. Schemas are
  referenced via SCIDs, which are tuples of (Schema name, Revision ID).
  Schemas are retrieved via HTTP and then cached in-memory. Validation
  uses :module:`jsonschema`.

"""
from __future__ import unicode_literals

import jsonschema
import logging

from .compat import json, http_get

__all__ = ('CAPSULE_SCID', 'get_schema', 'SCHEMA_URL_FORMAT',
           'post_validation_fixups', 'validate')


#: URL of index.php on the schema wiki (same as
#: '$wgEventLoggingSchemaApiUri').
SCHEMA_WIKI_API = 'http://meta.wikimedia.org/w/api.php'

#: Template for schema article URLs. Interpolates a revision ID.
SCHEMA_URL_FORMAT = SCHEMA_WIKI_API + '?action=jsonschema&revid=%s'

#: Schemas retrieved via HTTP are cached in this dictionary.
schema_cache = {}

#: SCID of the metadata object which wraps each event.
CAPSULE_SCID = ('EventCapsule', 8326736)


def get_schema(scid, encapsulate=False):
    """Get schema from memory or HTTP."""
    schema = schema_cache.get(scid)
    if schema is None:
        schema = http_get_schema(scid)
        schema_cache[scid] = schema
    # We depart from the JSON Schema specifications by disallowing
    # additional properties by default.
    # See `<https://bugzilla.wikimedia.org/show_bug.cgi?id=44454>`_.
    schema.setdefault('additionalProperties', False)
    if encapsulate:
        capsule = get_schema(CAPSULE_SCID)
        capsule['properties']['event'] = schema
        return capsule
    return schema


def http_get_schema(scid):
    """Retrieve schema via HTTP."""
    schema_name, revision_id = scid
    url = SCHEMA_URL_FORMAT % revision_id
    try:
        schema = json.loads(http_get(url))
    except (ValueError, EnvironmentError) as ex:
        raise jsonschema.SchemaError('Schema fetch failure: %s' % ex)
    jsonschema.Draft3Validator.check_schema(schema)
    return schema


def delete_if_exists_and_length_mismatches(capsule, field, expected_length):
    """Remove the field from the capsule's event, if it exists and
    does not have a length of expected_length.

    Relies on capsule being valid.
    """
    try:
        value = capsule['event'][field]
        actual_length = len(value)
        if actual_length != expected_length:
            del capsule['event'][field]
            logging.error(
                'Post validation fixup: {0}_{1}, removing field {2} '
                'because length is {3} instead of {4}'.format(
                    capsule['schema'],
                    capsule['revision'],
                    field,
                    actual_length,
                    expected_length
                )
            )
    except KeyError:
        # capsule['event'][event_field] does not exist
        # That's ok. Nothing to fixup.
        pass


def post_validation_fixups(capsule):
    """Clean known harmfull or semantically wrong data from validated
    capsules.

    This function never turns a valid capsule into an invalid one.
    """
    # Add checks only sparingly to this function.
    #
    # Do not use this function to implement more thorough checking of
    # existing schemas. Refine the schemas to achieve that, and refine
    # the client code to send the proper values.
    #
    # Instead, use this function only to clean up a few fields that
    # are known to be harmful and (after cleaning up client code) are
    # still arriving in the EventLogging pipeline, but do not warrant
    # throwing away the whole event.

    # As the capsule is required to be valid, schema and revision keys
    # have to exist.
    schema = capsule['schema']

    if schema == 'MultimediaViewerDuration':
        if capsule['revision'] in [8318615, 8572641]:
            # Cleanup against session cookies leaking in.
            # See bug #66478
            #
            # TODO: Please check after 2014-09-13, and remove if
            # clients stopped sending sessionId.
            delete_if_exists_and_length_mismatches(capsule, 'country', 2)
    elif schema == 'MultimediaViewerNetworkPerformance':
        if capsule['revision'] == 7917896:
            # Cleanup against session cookies leaking in.
            # See bug #66478
            #
            # TODO: Please check after 2014-09-13, and remove if
            # clients stopped sending sessionId.
            delete_if_exists_and_length_mismatches(capsule, 'country', 2)
    elif schema == 'NavigationTiming':
        if capsule['revision'] in [7494934, 8365252]:
            # Cleanup against session cookies leaking in.
            # See bug #66478
            #
            # TODO: Please check after 2014-09-13, and remove if
            # clients stopped sending sessionId.
            delete_if_exists_and_length_mismatches(capsule, 'originCountry', 2)


def get_scid(event):
    """Extract a SCID from an event."""
    return event['schema'], event['revision']


def validate(capsule):
    """Validates an encapsulated event.
    :raises :exc:`jsonschema.ValidationError`: If event is invalid.
    """
    try:
        scid = get_scid(capsule)
    except KeyError as ex:
        # If `schema` or `revision` keys are missing, a KeyError
        # exception will be raised. We re-raise it as a
        # :exc:`ValidationError` to provide a simpler API for callers.
        raise jsonschema.ValidationError('Missing key: %s' % ex)
    if capsule['revision'] < 1:
        raise jsonschema.ValidationError(
            'Invalid revision ID: %(revision)s' % capsule)
    schema = get_schema(scid, encapsulate=True)
    jsonschema.Draft3Validator(schema).validate(capsule)
    post_validation_fixups(capsule)
