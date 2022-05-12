<?php

namespace MediaWiki\Extension\EventLogging\MetricsPlatform;

use IContextSource;
use MediaWiki\Extension\EventLogging\EventSubmitter\EventSubmitter;
use MediaWiki\Extension\EventLogging\MetricsPlatform\EventSubmitter as MetricsPlatformEventSubmitter;
use Psr\Log\LoggerInterface;
use Wikimedia\MetricsPlatform\MetricsClient;
use Wikimedia\MetricsPlatform\StreamConfig\StreamConfigFactory;

class MetricsClientFactory {

	/** @var ContextAttributesFactory */
	private $contextAttributesFactory;

	/** @var EventSubmitter */
	private $eventSubmitter;

	/** @var array|bool */
	private $streamConfigs;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(
		ContextAttributesFactory $contextAttributesFactory,
		EventSubmitter $eventSubmitter,
		$streamConfigs,
		LoggerInterface $logger
	) {
		$this->contextAttributesFactory = $contextAttributesFactory;
		$this->eventSubmitter = $eventSubmitter;
		$this->streamConfigs = $streamConfigs;
		$this->logger = $logger;
	}

	public function newMetricsClient( IContextSource $requestContext ): MetricsClient {
		$eventSubmitter = new MetricsPlatformEventSubmitter( $this->eventSubmitter );
		$integration = new Integration( $this->contextAttributesFactory, $requestContext );

		// TODO: EventStreamConfig (and EventLogging to some extent) and the PHP Metrics Platform
		//  Client have representations of stream configs. Extract a single representation into a
		//  library.
		$streamConfigs = new StreamConfigFactory( $this->streamConfigs );

		return new MetricsClient( $eventSubmitter, $integration, $streamConfigs, $this->logger );
	}
}
