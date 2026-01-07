<?php

namespace Core\Events;

use Core\Psr\EventDispatcher\EventDispatcherInterface;
use Core\Psr\EventDispatcher\StoppableEventInterface;

/**
 * A PSR-14 compliant service responsible for retrieving Listeners 
 * from a Listener Provider for the Event dispatched.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class EventDispatcher implements EventDispatcherInterface
{
    /** @var \Core\Events\ListenerProvider */
    private $listenerProvider;

    /**
     * @param \Core\Events\ListenerProvider $listenerProvider
     * 
     * @return void
     */
    public function __construct(ListenerProvider $provider)
    {
        $this->listenerProvider = $provider;
    }

    /**
     * Gets the Event listener provider.
     * 
     * @return \Core\Events\ListenerProvider
     */
    public function listenerProvider()
    {
        return $this->listenerProvider;
    }

    /**
     * Provides all relevant listeners with an event to process.
     *
     * @param object $event The object to process
     * @return object The Event that was passed, now modified by listeners
     */
    public function dispatch(object $event)
    {
        $listeners = $this->listenerProvider->getListenersForEvent($event);
        foreach ($listeners as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }
            if (is_callable($listener)) {
                $listener($event);
            }
        }

        return $event;
    }
}
