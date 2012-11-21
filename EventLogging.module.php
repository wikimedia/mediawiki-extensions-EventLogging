<?php
/**
 * ResourceLoaderModule subclass for making event data models
 * available as JavaScript submodules to client-side code.
 *
 * @file
 * @author Ori Livneh <ori@wikimedia.org>
 */

/**
 * Packages the global (cross-wiki) set of data models as a JavaScript
 * ResourceLoader module. The models are canonically stored in a JS
 * article on Meta. This module attempts to retrieve the set of models
 * from memcached and default to an HTTP request if the key is missing.
 *
 * To prevent a cache stampede, only one thread of execution is
 * permitted to attempt an HTTP request for the data models. Other
 * threads simply generate an empty object.
 */
class ResourceLoaderEventDataModels extends ResourceLoaderModule {

	const CACHE_KEY = 'ext.eventLogging:dataModels';
	const LOCK_TIMEOUT = 30;


	public function getDependencies() {
		return array( 'ext.eventLogging.core' );
	}


	/**
	 * Attempt to retrieve models via HTTP.
	 *
	 * @return array|null: Decoded JSON object or null on failure.
	 */
	private function httpGetModels() {
		global $wgEventLoggingModelsUri;

		if ( !$wgEventLoggingModelsUri ) {
			return;
		}

		// The HTTP request timeout is set to a fraction of the lock timeout to
		// prevent a pile-up of multiple lingering connections.
		$res = Http::get( $wgEventLoggingModelsUri, self::LOCK_TIMEOUT * 0.8 );
		if ( $res === false ) {
			wfDebugLog( 'EventLogging', 'Failed to retrieve data models via HTTP' );
			return;
		}

		$models = FormatJson::decode( $res, true );
		if ( !is_array( $models ) ) {
			wfDebugLog( 'EventLogging', 'Failed to decode model JSON data' );
			return;
		}

		return $models;
	}


	/**
	 * Get the last modified timestamp of this module.
	 *
	 * The last modified timestamp is be updated automatically by an
	 * PageContentSaveComplete hook handler in EventLogging.home.php
	 * whenever the models page is saved. If the key is missing, we
	 * default to setting the last modified time to now.
	 *
	 * @param $context ResourceLoaderContext
	 * @return integer: Unix timestamp
	 */
	public function getModifiedTime( ResourceLoaderContext $context ) {
		global $wgMemc;

		$key = self::CACHE_KEY . ':mTime';
		$mTime = $wgMemc->get( $key );

		if ( !$mTime ) {
			$mTime = wfTimestampNow();
			$wgMemc->add( $key, $mTime );
		}

		return $mTime;
	}


	/**
	 * Retrieves data models from cache or HTTP and generates a JavaScript
	 * expression which assigns them to mediaWiki.eventLogging.dataModels.
	 * If unable to retrieve data models, sets the value to an empty object
	 * instead.
	 *
	 * @param $context ResourceLoaderContext
	 * @return string
	 */
	public function getScript( ResourceLoaderContext $context ) {
		global $wgMemc;

		$models = $wgMemc->get( self::CACHE_KEY );

		if ( $models === false ) {
			// Attempt to acquire exclusive update lock. If successful,
			// grab models via HTTP and update the cache.
			if ( $wgMemc->add( self::CACHE_KEY . ':lock', 1, self::LOCK_TIMEOUT ) ) {
				$models = self::httpGetModels();
				if ( $models ) {
					$wgMemc->add( self::CACHE_KEY, $models );
				}
			}
		}

		if ( !$models ) {
			$models = new stdClass();  // Will be encoded as empty JS object.
		}

		return Xml::encodeJsCall( 'mediaWiki.eventLog.setModels', array( $models ) );
	}
}
