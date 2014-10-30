<?php namespace Kevindierkx\Elicit\Connection;

use \Kevindierkx\Elicit\Query\Grammars\Grammar;
use \Kevindierkx\Elicit\Query\Processors\FractalProcessor;

class FractalConnection extends Connection {

	/**
	 * {@inheritdoc}
	 */
	protected function getDefaultQueryGrammar()
	{
		return new Grammar;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getDefaultPostProcessor()
	{
		return new FractalProcessor;
	}

}
