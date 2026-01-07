<?php

namespace Core\Events;

use Core\Psr\EventDispatcher\ListenerProviderInterface;

/**
 * A PSR-14 compliant service 
 * responsible for determining what Listeners are relevant to 
 * and should be called for a given Event.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class ListenerProvider implements ListenerProviderInterface
{
    /** @var array */
    protected $listeners = [];

    /**
     * @param object $event An event for which to return the relevant listeners.
     * @return iterable<callable>
     *   An iterable (array, iterator, or generator) of callables.  Each
     *   callable MUST be type-compatible with $event.
     */
    public function getListenersForEvent(object $event): iterable
    {
        $event_type = get_class($event);
        return array_key_exists($event_type, $this->listeners) ? $this->listeners[$event_type] : [];
    }

    /**
     * Registers any callable function by event type.
     * 
     * @param string $type
     * @param callable $callable
     * @return self
     */
    public function addListener(string $type, callable $callable)
    {
        $this->listeners[$type][] = $callable;
        return $this;
    }

    /**
     * Clears any registered Listeners by type.
     * 
     * @param string $type
     * @return void
     */
    public function clearListeners(string $type)
    {
        if (array_key_exists($type, $this->listeners)) {
            unset($this->listeners[$type]);
        }
    }
}
