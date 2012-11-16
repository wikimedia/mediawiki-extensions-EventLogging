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

	const CACHE_EXPIRY = 2419200; // 28 days.
	const LOCK_TIMEOUT = 30;

	private static $memcKey = 'ext.EventLogging:DataModels';


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
	 * The last modified timestamp should be updated automatically by an
	 * PageContentSaveComplete hook handler on the wiki that is hosting the
	 * models. If the key is missing, we default to setting the last modified
	 * time to now.
	 *
	 * @param $context ResourceLoaderContext
	 * @return integer: Unix timestamp
	 */
	public function getModifiedTime( ResourceLoaderContext $context ) {
		global $wgMemc;

		// TODO(ori-l, 13-Nov-2012): Ensure this key is updated by a hook
		// handler on the host wiki.
		$key = self::$memcKey . ':mTime';
		$mTime = $wgMemc->get( $key );

		if ( !$mTime ) {
			$mTime = wfTimestampNow();
			$wgMemc->add( $key, $mTime, self::CACHE_EXPIRY );
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

		$models = $wgMemc->get( self::$memcKey );

		if ( $models === false ) {
			// Attempt to acquire exclusive update lock. If successful,
			// grab models via HTTP and update the cache.
			if ( $wgMemc->add( self::$memcKey . ':lock', 1, self::LOCK_TIMEOUT ) ) {
				$res = Http::get( $wgEventLoggingModelsUri, self::LOCK_TIMEOUT * 0.8 );
				if ( $models ) {
					$wgMemc->add( self::$memcKey, $models, self::CACHE_EXPIRY );
				}
			}
		}

		if ( !$models ) {
			$models = new stdClass();  // Will be encoded as empty JS object.
		}

		return Xml::encodeJsCall( 'mediaWiki.eventLog.initModels', $models );
	}
}
