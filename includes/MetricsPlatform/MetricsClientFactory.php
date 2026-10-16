<?php

namespace MediaWiki\Extension\EventLogging\MetricsPlatform;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\EventLogging\EventSubmitter\EventSubmitter;
use MediaWiki\Extension\EventLogging\MetricsPlatform\EventSubmitter as MetricsPlatformEventSubmitter;
use Psr\Log\LoggerInterface;
use Wikimedia\MetricsPlatform\MetricsClient;
use Wikimedia\MetricsPlatform\StreamConfig\StreamConfigFactory;

/**
 * Factory for legacy Metrics Platform clients.
 *
 * @deprecated since 1.48 Use the Test Kitchen PHP SDK instead, via the
 * TestKitchen.InstrumentManager service.
 * @see https://wikitech.wikimedia.org/wiki/Test_Kitchen/SDK/PHP_SDK
 */
class MetricsClientFactory {

	/** @var ContextAttributesFactory */
	private $contextAttributesFactory;

	/** @var EventSubmitter */
	private $eventSubmitter;

	/** @var array|false */
	private $streamConfigs;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param ContextAttributesFactory $contextAttributesFactory
	 * @param EventSubmitter $eventSubmitter
	 * @param array|false $streamConfigs
	 * @param LoggerInterface $logger
	 */
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

	/**
	 * Create a legacy Metrics Platform client.
	 *
	 * @param IContextSource $requestContext
	 * @return MetricsClient
	 * @deprecated since 1.48 Use the Test Kitchen PHP SDK instead, via the
	 * TestKitchen.InstrumentManager service and Instrument::send().
	 * @see https://wikitech.wikimedia.org/wiki/Test_Kitchen/SDK/PHP_SDK
	 */
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
