<?php namespace Kevindierkx\Elicit\Elicit;

class ModelNotFoundException extends \RuntimeException
{
    /**
     * Name of the affected Eloquent model.
     *
     * @var string
     */
    protected $model;

    /**
     * Set the affected Eloquent model.
     *
     * @param  string   $model
     * @return self
     */
    public function setModel($model)
    {
        $this->model = $model;

        $this->message = "No API results for model [{$model}].";

        return $this;
    }

    /**
     * Get the affected Eloquent model.
     *
     * @return string
     */
    public function getModel()
    {
        return $this->model;
    }
}
