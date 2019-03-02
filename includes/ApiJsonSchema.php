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

	/**
	 * Restrict the set of valid formatters to just 'json' and 'jsonfm'.  Other
	 * requested formatters are instead treated as 'json'.
	 * @return ApiFormatJson
	 */
	public function getCustomPrinter() {
		if ( $this->getMain()->getVal( 'format' ) === 'jsonfm' ) {
			$format = 'jsonfm';
		} else {
			$format = 'json';
		}
		return $this->getMain()->createPrinterByName( $format );
	}

	public function getAllowedParams() {
		return [
			'revid' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true,
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
		$rev = Revision::newFromId( $params['revid'] );

		if ( !$rev ) {
			$this->dieWithError( [ 'apierror-nosuchrevid', $params['revid'] ], null, null, 400 );
		}

		$title = $rev->getTitle();
		if ( !$title || !$title->inNamespace( NS_SCHEMA ) ) {
			$this->dieWithError( [ 'apierror-invalidtitle', wfEscapeWikiText( $title ) ], null, null, 400 );
		}

		/** @var JsonSchemaContent $content */
		$content = $rev->getContent();
		if ( !$content ) {
			$this->dieWithError( [ 'apierror-nosuchrevid', $params['revid'] ], null, null, 400 );
		}

		// We use the revision ID for lookup; the 'title' parameter is
		// optional. If present, it is used to assert that the specified
		// revision ID is indeed a revision of a page with the specified
		// title. (Bug 46174)
		if ( $params['title'] && !$title->equals( Title::newFromText( $params['title'], NS_SCHEMA ) ) ) {
			$this->dieWithError(
				[ 'apierror-revwrongpage', $params['revid'], wfEscapeWikiText( $params['title'] ) ],
				null, null, 400
			);
		}

		$this->markCacheable( $rev );
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
