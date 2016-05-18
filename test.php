<?php

function dd($x)
{
    die(var_dump($x));
}

require __DIR__.'/vendor/autoload.php';

use Kevindierkx\Elicit\ApiManager;
use Kevindierkx\Elicit\Elicit\Model;

$elicit = new ApiManager;

$elicit->addConnection(
    [
        'urlApi' => 'http://api.pcextreme.nl/',
    ],
    'test'
);

// $connection = $elicit->getConnection('test');
// $connection->setQueryGrammar(new FlarumGrammar);
// $connection->setPostProcessor(new FlarumProcessor);

class TestModel extends Model
{
    protected $connection = 'test';

    protected $paths = [
        'index' => [
            'method' => 'GET',
            'path'   => 'business-hours',
        ],
    ];
}

$model = new TestModel;

dd($model->get());
