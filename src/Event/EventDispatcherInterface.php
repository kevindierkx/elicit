<?php

namespace Kevindierkx\Elicit\Event;

interface EventDispatcherInterface
{
    /**
     * Dispatches an event to all registered listeners.
     * When the event is not supplied, an empty Event instance is created.
     *
     * @param  string  $eventName
     * @param  Event   $event
     * @return Event
     */
    public function dispatch($eventName, Event $event = null);
}
