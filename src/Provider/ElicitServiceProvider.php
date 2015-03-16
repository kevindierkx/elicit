<?php namespace Kevindierkx\Elicit\Provider;

use Illuminate\Support\ServiceProvider;
use Kevindierkx\Elicit\ApiManager;
use Kevindierkx\Elicit\Elicit\Model;
use Kevindierkx\Elicit\ConnectionFactory;

class ElicitServiceProvider extends ServiceProvider {

	/**
	 * {@inheritdoc}
	 */
	public function boot()
	{
		$this->package('kevindierkx/elicit', 'elicit', __DIR__ . '/..');

		Model::setConnectionResolver($this->app['elicit']);

		Model::setEventDispatcher($this->app['events']);
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
