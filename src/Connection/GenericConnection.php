<?php

namespace Kevindierkx\Elicit\Connection;

use Kevindierkx\Elicit\Query\Grammars\Grammar;
use Kevindierkx\Elicit\Query\Processors\Processor;

class GenericConnection extends AbstractConnection
{
    /**
     * Get the default query grammar instance.
     *
     * @return Grammar
     */
    public function getDefaultQueryGrammar()
    {
        return new Grammar;
    }

    /**
     * Get the default post processor instance.
     *
     * @return Processor
     */
    public function getDefaultPostProcessor()
    {
        return new Processor;
    }
}
