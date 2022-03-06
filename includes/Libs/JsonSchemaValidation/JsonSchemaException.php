<?php
/**
 * JSON Schema Validation Library
 *
 * Copyright (c) 2005-2012, Rob Lanphier
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are
 * met:
 *
 * 	* Redistributions of source code must retain the above copyright
 * 	  notice, this list of conditions and the following disclaimer.
 *
 * 	* Redistributions in binary form must reproduce the above
 * 	  copyright notice, this list of conditions and the following
 * 	  disclaimer in the documentation and/or other materials provided
 * 	  with the distribution.
 *
 * 	* Neither my name nor the names of my contributors may be used to
 * 	  endorse or promote products derived from this software without
 * 	  specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Rob Lanphier <robla@wikimedia.org>
 * @copyright © 2011-2012 Rob Lanphier
 * @license http://jsonwidget.org/LICENSE BSD-3-Clause
 */

namespace MediaWiki\Extension\EventLogging\Libs\JsonSchemaValidation;

use Exception;

class JsonSchemaException extends Exception {

	/**
	 * Arguments for the message
	 *
	 * @var array
	 */
	public $args;

	/**
	 * @var string 'validate-fail' or 'validate-fail-null'
	 */
	public $subtype;

	/**
	 * @param string|int $code
	 * @param string ...$args
	 */
	public function __construct( $code, ...$args ) {
		parent::__construct( $code );
		$this->code = $code;
		$this->args = $args;
	}
}

class_alias( JsonSchemaException::class, 'JsonSchemaException' );
