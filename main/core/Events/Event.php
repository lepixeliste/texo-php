<?php

namespace Core\Events;

use Serializable;
use Core\Psr\EventDispatcher\StoppableEventInterface;

/**
 * PSR-14 compliant Core Event class. 
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class Event implements StoppableEventInterface, Serializable
{
    /**
     * The event name
     *
     * @var string
     */
    protected $name = '';

    /**
     * The event emitter
     *
     * @var object|null
     */
    protected $emitter;

    /**
     * Whether no further event listeners should be triggered
     * 
     * @var bool
     */
    protected $propagationStopped = false;

    public function __construct(string $name, object $emitter = null)
    {
        $this->name = $name;
        $this->emitter = $emitter;
    }

    /**
     * Gets event name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Gets target/context from which event was emitted
     *
     * @return object|null
     */
    public function getEmitter()
    {
        return $this->emitter;
    }

    /**
     * Is propagation stopped?
     *
     * This will typically only be used by the Dispatcher to determine if the
     * previous listener halted propagation.
     *
     * @return bool
     *   True if the Event is complete and no further listeners should be called.
     *   False to continue calling listeners.
     */
    public function isPropagationStopped(): bool
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
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    /**
     * String representation of the event. 
     * 
     * @return string
     */
    public function serialize(): string
    {
        $encode = json_encode($this->__serialize());
        return $encode !== false ? $encode : '';
    }

    /**
     * Constructs the event from array. 
     * 
     * @return void
     */
    public function unserialize(string $data): void
    {
        $json = json_decode($data, true);
        if (!$json) {
            return;
        }
        $this->__unserialize($json);
    }

    /**
     * Data representation of the event. 
     * 
     * @return mixed
     */
    public function __serialize()
    {
        return [
            'name' => $this->name,
            'emitter' => [
                'name' => is_object($this->emitter) ? get_class($this->emitter) : null,
                'data' => is_object($this->emitter) && $this->emitter instanceof Serializable ? $this->emitter->serialize() : null
            ]
        ];
    }

    /**
     * Constructs the event from array. 
     * 
     * @return void
     */
    public function __unserialize(array $data)
    {
        $this->name = isset($data['name']) ? $data['name'] : '';
        $emitter_array = isset($data['emitter']) ? $data['emitter'] : null;
        $emitter_class = is_array($emitter_array) ? $emitter_array['name'] : '';
        if (!empty($emitter_class)) {
            $emitter = new $emitter_class();
            if ($this->emitter instanceof Serializable) {
                $emitter_data = is_array($emitter_array) ? $emitter_array['data'] : '';
                $emitter->unserialize($emitter_data);
            }
        }
    }
}
