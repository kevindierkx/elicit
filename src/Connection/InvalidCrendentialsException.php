<?php namespace Kevindierkx\Elicit\Elicit;

class InvalidCredentialsException extends \RuntimeException {

	/**
	 * Name of the affected connection.
	 *
	 * @var string
	 */
	protected $connection;

	/**
	 * Set the affected connection.
	 *
	 * @param  string   $connection
	 * @return $this
	 */
	public function setConnection($connection)
	{
		$this->connection = $connection;

		$this->message = "Credentials could not be verified for connection [{$connection}].";

		return $this;
	}

	/**
	 * Get the affected connection.
	 *
	 * @return string
	 */
	public function getConnection()
	{
		return $this->connection;
	}

}
