<?php
/**
 * Represents a schema revision on a remote wiki.
 * Handles retrieval (via HTTP) and local caching.
 * @note When we switch to PHP 5.4, add 'implements JsonSerializable'
 */
class RemoteSchema {

	const LOCK_TIMEOUT = 20;

	var $cache;
	var $http;
	var $key;
	var $revision;
	var $title;
	var $content = false;


	/**
	 * Constructor.
	 * @param string $title
	 * @param integer $revision
	 * @param ObjectCache $cache: (optional) cache client.
	 * @param Http $http: (optional) HTTP client.
	 */
	function __construct( $title, $revision, $cache = NULL, $http = NULL ) {
		global $wgEventLoggingDBname;

		$this->title = $title;
		$this->revision = $revision;
		$this->cache = $cache ?: wfGetCache( CACHE_ANYTHING );
		$this->http = $http ?: new Http();
		$this->key = "schema:{$wgEventLoggingDBname}:{$title}:{$revision}";
	}


	/**
	 * Retrieves schema content.
	 * @return array|bool: Schema or false if irretrievable.
	 */
	function get() {
		if ( $this->content ) {
			return $this->content;
		}

		$this->content = $this->memcGet();
		if ( $this->content ) {
			return $this->content;
		}

		$this->content = $this->httpGet();
		if ( $this->content ) {
			$this->memcSet();
		}

		return $this->content;
	}


	/**
	 * Retrieves content from memcached.
	 * @return array:bool: Schema or false if not in cache.
	 */
	function memcGet() {
		return $this->cache->get( $this->key );
	}


	/**
	 * Store content in memcached.
	 */
	function memcSet() {
		return $this->cache->set( $this->key, $this->content );
	}


	/**
	 * Acquire a mutex lock for HTTP retrieval.
	 * @return bool: Whether lock was successfully acquired.
	 */
	function lock() {
		return $this->cache->add( $this->key . ':lock', 1, self::LOCK_TIMEOUT );
	}


	/**
	 * Constructs URI for retrieving schema from remote wiki.
	 * @return string: URI.
	 */
	function getUri() {
		global $wgEventLoggingSchemaIndexUri;

		$q = array(
			'title'  =>  'Schema:' . $this->title,
			'action' =>  'raw',
			'oldid'  =>  $this->revision
		);

		return wfAppendQuery( $wgEventLoggingSchemaIndexUri, $q );
	}


	/**
	 * Returns an object containing serializable properties.
	 * @implements JsonSerializable
	 */
	function jsonSerialize() {
		return array(
			'schema'   => $this->get() ?: new StdClass(),
			'revision' => $this->revision
		);
	}


	/**
	 * Retrieves the schema using HTTP.
	 * Uses a memcached lock to avoid cache stampedes.
	 * @return array|boolean: Schema or false if unable to fetch.
	 */
	function httpGet() {
		if ( !$this->lock() ) {
			return false;
		}
		$raw = $this->http->get( $this->getUri(), self::LOCK_TIMEOUT * 0.8 );
		return FormatJson::decode( $raw, true ) ?: false;
	}
}
