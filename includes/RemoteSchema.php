<?php
/**
 * Represents a schema revision on a remote wiki.
 * Handles retrieval (via HTTP) and local caching.
 * @note When we switch to PHP 5.4, add 'implements JsonSerializable'
 */
class RemoteSchema {

	const LOCK_TIMEOUT = 20;

	public $title;
	public $revision;
	public $cache;
	public $http;
	public $key;
	public $content = false;


	/**
	 * Constructor.
	 * @param string $title
	 * @param integer $revision
	 * @param ObjectCache $cache (optional) cache client.
	 * @param Http $http (optional) HTTP client.
	 */
	public function __construct( $title, $revision, $cache = NULL, $http = NULL ) {
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
	public function get() {
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
	 * Returns an object containing serializable properties.
	 * @implements JsonSerializable
	 */
	public function jsonSerialize() {
		return array(
			'schema'   => $this->get() ?: new StdClass(),
			'revision' => $this->revision
		);
	}


	/**
	 * Retrieves content from memcached.
	 * @return array:bool: Schema or false if not in cache.
	 */
	protected function memcGet() {
		return $this->cache->get( $this->key );
	}


	/**
	 * Store content in memcached.
	 */
	protected function memcSet() {
		return $this->cache->set( $this->key, $this->content );
	}


	/**
	 * Retrieves the schema using HTTP.
	 * Uses a memcached lock to avoid cache stampedes.
	 * @return array|boolean: Schema or false if unable to fetch.
	 */
	protected function httpGet() {
		if ( !$this->lock() ) {
			return false;
		}
		$raw = $this->http->get( $this->getUri(), array(
			'timeout' => self::LOCK_TIMEOUT * 0.8
		) );
		return FormatJson::decode( $raw, true ) ?: false;
	}


	/**
	 * Acquire a mutex lock for HTTP retrieval.
	 * @return bool: Whether lock was successfully acquired.
	 */
	protected function lock() {
		return $this->cache->add( $this->key . ':lock', 1, self::LOCK_TIMEOUT );
	}


	/**
	 * Constructs URI for retrieving schema from remote wiki.
	 * @return string: URI.
	 */
	protected function getUri() {
		global $wgEventLoggingSchemaApiUri;

		if ( substr( $wgEventLoggingSchemaApiUri, -10 ) === '/index.php' ) {
			// Old-style request (index.php)
			$q = array(
				'action' =>  'raw',
				'oldid'  =>  $this->revision,
			);
		} else {
			// New-style request (api.php)
			$q = array(
				'action' =>  'jsonschema',
				'revid'  =>  $this->revision
			);
		}

		return wfAppendQuery( $wgEventLoggingSchemaApiUri, $q );
	}
}
