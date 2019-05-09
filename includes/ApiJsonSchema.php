<?php
/**
 * API module for retrieving JSON Schema.
 *
 * @file
 * @ingroup EventLogging
 * @ingroup Extensions
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

/**
 * API module for retrieving JSON Schema.
 * This avoids API result paths and returns HTTP error codes in order to
 * act like a request for the raw page content.
 * @ingroup API
 */
class ApiJsonSchema extends ApiBase {

	public function getAllowedParams() {
		return [
			'revid' => [
				ApiBase::PARAM_TYPE => 'integer',
			],
			'title' => [
				ApiBase::PARAM_TYPE => 'string',
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=jsonschema&revid=1234'
				=> 'apihelp-jsonschema-example-1',
			'action=jsonschema&title=Test'
				=> 'apihelp-jsonschema-example-2',
		];
	}

	/**
	 * Set headers on the pending HTTP response.
	 * @param Revision $rev
	 */
	protected function markCacheable( Revision $rev ) {
		$main = $this->getMain();
		$main->setCacheMode( 'public' );
		$main->setCacheMaxAge( 300 );

		$lastModified = wfTimestamp( TS_RFC2822, $rev->getTimestamp() );
		$main->getRequest()->response()->header( "Last-Modified: $lastModified" );
	}

	public function execute() {
		$params = $this->extractRequestParams();

		if ( !isset( $params['revid'] ) && !isset( $params['title'] ) ) {
			$this->dieWithError(
				[ 'apierror-missingparam-at-least-one-of', 'revid', 'title' ],
				null, null, 400
			);
		}

		// If we are given revid, then look up Revision and
		// verify that $params['title'] (if given) matches.
		if ( isset( $params['revid'] ) ) {
			$rev = Revision::newFromId( $params['revid'] );
			if ( !$rev ) {
				$this->dieWithError(
					[ 'apierror-nosuchrevid', $params['revid'] ], null, null, 400
				);
			}
			$title = $rev->getTitle();
			if ( !$title || !$title->inNamespace( NS_SCHEMA ) ) {
				$this->dieWithError(
					[ 'apierror-invalidtitle', wfEscapeWikiText( $title ) ], null, null, 400
				);
			}

			// If we use the revid param for lookup; the 'title' parameter is
			// optional. If present, it is used to assert that the specified
			// revision ID is indeed a revision of a page with the specified
			// title. (Bug 46174)
			if (
				$params['title'] &&
				!$title->equals( Title::newFromText( $params['title'], NS_SCHEMA ) )
			) {
				$this->dieWithError(
					[ 'apierror-revwrongpage', $params['revid'], wfEscapeWikiText( $params['title'] ) ],
					null, null, 400
				);
			}

		// Else use $params['title'] and get the latest revision
		} else {
			$title = Title::newFromText( $params['title'], NS_SCHEMA );
			if ( !$title || !$title->exists() || !$title->inNamespace( NS_SCHEMA ) ) {
				$this->dieWithError(
					[ 'apierror-invalidtitle', wfEscapeWikiText( $title ) ], null, null, 400
				);
			}

			$rev = Revision::newFromId( $title->getLatestRevID() );
		}

		/** @var JsonSchemaContent $content */
		$content = $rev->getContent();
		if ( !$content ) {
			$this->dieWithError( [ 'apierror-nosuchrevid', $rev->getId() ], null, null, 400 );
		}

		$this->markCacheable( $rev );
		'@phan-var JsonSchemaContent $content';
		$schema = $content->getJsonData();

		$result = $this->getResult();
		$result->addValue( null, 'title', $title->getText() );
		foreach ( $schema as $k => &$v ) {
			if ( $k === 'properties' ) {
				foreach ( $v as &$properties ) {
					$properties[ApiResult::META_BC_BOOLS] = [ 'required' ];
				}
			}
			$result->addValue( null, $k, $v );
		}
	}
}
