<?php namespace PCextreme\Api\Provider;

use Illuminate\Support\ServiceProvider;
use PCextreme\Api\ApiManager;
use PCextreme\Api\Elicit\Model;
use PCextreme\Api\Connection\ConnectionFactory;

class ApiServiceProvider extends ServiceProvider {

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		Model::setConnectionResolver($this->app['elicit']);

		Model::setEventDispatcher($this->app['events']);
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
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
