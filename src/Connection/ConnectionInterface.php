<?php namespace Kevindierkx\Elicit\Connection;

interface ConnectionInterface {

	/**
	 * Get request.
	 *
	 * @param  array  $query
	 * @return array
	 */
	public function get($path, $query = array());

	/**
	 * Post request.
	 *
	 * @param  array  $query
	 * @param  array  $postBody
	 * @return array
	 */
	public function post($path, $query = array(), $postBody = array());

	/**
	 * Update request.
	 *
	 * @param  array  $query
	 * @param  array  $postBody
	 * @return array
	 */
	public function put($path, $query = array(), $postBody = array());

	/**
	 * Update request.
	 *
	 * @param  array  $query
	 * @param  array  $postBody
	 * @return array
	 */
	public function patch($path, $query = array(), $postBody = array());

	/**
	 * Delete request.
	 *
	 * @param  array  $query
	 * @return array
	 */
	public function delete($path, $query = array());

	/**
	 * Options request.
	 *
	 * @param  array  $query
	 * @return array
	 */
	public function options($path, $query = array());

}
