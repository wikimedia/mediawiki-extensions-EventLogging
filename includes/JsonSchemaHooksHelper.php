<?php

namespace MediaWiki\Extension\EventLogging;

use MediaWiki\Config\ServiceOptions;

class JsonSchemaHooksHelper {
	public const CONSTRUCTOR_OPTIONS = [
		'EventLoggingDBname',
		'DBname',
	];

	private ServiceOptions $options;

	public function __construct( ServiceOptions $options ) {
		$this->options = $options;
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Convenience function to determine whether the Schema namespace is enabled.
	 *
	 * @return bool
	 */
	public function isSchemaNamespaceEnabled(): bool {
		return $this->options->get( 'EventLoggingDBname' ) === $this->options->get( 'DBname' );
	}
}
