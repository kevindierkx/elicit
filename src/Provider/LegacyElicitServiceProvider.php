<?php namespace Kevindierkx\Elicit\Provider;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\AliasLoader;
use Kevindierkx\Elicit\ApiManager;
use Kevindierkx\Elicit\Elicit\Model;
use Kevindierkx\Elicit\ConnectionFactory;

class LegacyElicitServiceProvider extends ServiceProvider {

	/**
	 * {@inheritdoc}
	 */
	public function boot()
	{
		$this->package('kevindierkx/elicit', 'elicit', __DIR__ . '/..');

		$this->prepareCompatibility();

		Model::setConnectionResolver($this->app['elicit']);

		// We can't alias the type hinted event dispatcher.
		// The application would break when using it, therefore
		// we don't set it. The model should work fine without it.
	 	//
		// Model::setEventDispatcher($this->app['events']);
	 	//
	}

	/**
	 * Prepare any compatibility for earlier or later versions of Laravel.
	 */
	protected function prepareCompatibility()
	{
		$loader = AliasLoader::getInstance();

		if (interface_exists('Illuminate\Support\Contracts\ArrayableInterface')) {
            $loader->alias('Illuminate\Contracts\Support\Arrayable', 'Illuminate\Support\Contracts\ArrayableInterface');
        }

        if (interface_exists('Illuminate\Support\Contracts\JsonableInterface')) {
            $loader->alias('Illuminate\Contracts\Support\Jsonable', 'Illuminate\Support\Contracts\JsonableInterface');
        }
	}

	/**
	 * {@inheritdoc}
	 */
	public function register()
	{
		// The connection factory is used to create the actual connection instances on
		// the database. We will inject the factory into the manager so that it may
		// make the connections while they are actually needed and not of before.
		$this->app->bindShared('elicit.factory', function($app) {
			return new ConnectionFactory($app);
		});

		// The database manager is used to resolve various connections, since multiple
		// connections might be managed. It also implements the connection resolver
		// interface which may be used by other components requiring connections.
		$this->app->bindShared('elicit', function($app) {
			return new ApiManager($app, $app['elicit.factory']);
		});
	}

}
