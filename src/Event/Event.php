<?php

namespace Kevindierkx\Elicit\Event;

class Event
{
    /**
     * @var bool
     */
    protected $propagationStopped = false;

    /**
     * Returns whether further event listeners should be triggered.
     *
     * @return bool
     */
    public function isPropagationStopped()
    {
        return $this->propagationStopped;
    }

    /**
     * Stops the propagation of the event to further event listeners.
     *
     * If multiple event listeners are connected to the same event, no
     * further event listener will be triggered once any trigger calls
     * stopPropagation().
     *
     * @return void
     */
    public function stopPropagation()
    {
        $this->propagationStopped = true;
    }
}
