<?php

namespace MediaWiki\Extension\EventLogging\MetricsPlatform;

use MediaWiki\Context\IContextSource;
use Wikimedia\MetricsPlatform\Integration as MetricsPlatformIntegration;

/**
 * Provides the following functionality for the Metrics Platform Client:
 *
 * 1. Sending events to a remote service via EventBus; and
 * 2. Gets the values of various context attributes from the execution environment
 *
 * Note well that #2 is done lazily, during the first call to `Integration#getContextAttribute()`
 * and not at construction time, which keeps the cost of instantiating `Integration` instances low.
 *
 * @internal
 */
class Integration implements MetricsPlatformIntegration {

	/**
	 * @var ContextAttributesFactory
	 */
	private $contextAttributesFactory;

	/**
	 * @var IContextSource
	 */
	private $contextSource;

	/**
	 * @var array
	 */
	private $contextAttributes;

	public function __construct(
		ContextAttributesFactory $contextAttributesFactory,
		IContextSource $contextSource
	) {
		$this->contextAttributesFactory = $contextAttributesFactory;
		$this->contextSource = $contextSource;
	}

	/** @inheritDoc */
	public function getContextAttribute( string $name ) {
		$contextAttributes =
			$this->contextAttributes ??= $this->contextAttributesFactory->newContextAttributes( $this->contextSource );

		return $contextAttributes[ $name ] ?? null;
	}
}
