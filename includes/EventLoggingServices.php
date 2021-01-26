<?php

use MediaWiki\MediaWikiServices;

class EventLoggingServices {

	/** @var MediaWikiServices */
	private $services;

	/** @return EventLoggingServices */
	public static function getInstance(): EventLoggingServices {
		return new self( MediaWikiServices::getInstance() );
	}

	/** @param MediaWikiServices $services */
	public function __construct( MediaWikiServices $services ) {
		$this->services = $services;
	}

	/** @return EventServiceClient */
	public function getEventServiceClient(): EventServiceClient {
		return $this->services->getService( 'EventServiceClient' );
	}

	public function getUserBucketProvider(): UserBucketProvider {
		return $this->services->getService( 'UserBucketProvider' );
	}

}
