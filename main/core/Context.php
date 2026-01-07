<?php

namespace Core;

use Core\Events\EventDispatcher;
use Core\Events\ListenerProvider;
use Core\Pdo\Db;

/**
 * The main app context.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class Context
{
    /**
     * The context id.
     *
     * @var string
     */
    private $id;

    /**
     * The Db instance.
     *
     * @var \Core\Pdo\Db
     */
    private $db;

    /**
     * The Listener provider.
     *
     * @var \Core\Events\ListenerProvider
     */
    private $listenerProvider;

    /**
     * The Event dispatcher.
     *
     * @var \Core\Events\EventDispatcher
     */
    private $dispatcher;

    /**
     * @param \Core\Pdo\Db $db
     * @param \Core\Events\ListenerProvider $listenerProvider
     * @return void
     */
    public function __construct(Db $db, ListenerProvider $listenerProvider)
    {
        $this->id = uniqid();
        $this->listenerProvider = $listenerProvider;
        $this->dispatcher = new EventDispatcher($listenerProvider);
        $this->db = $db;
    }

    /**
     * The context unique id.
     *
     * @return string
     */
    public function id()
    {
        return $this->id;
    }

    /**
     * The main app database.
     *
     * @return \Core\Pdo\Db
     */
    public function db()
    {
        return $this->db;
    }

    /**
     * Listens to an Event type.
     * 
     * @param  string $eventType The event type to listen to
     * @param  callable $callable The callback function to use
     * @return void
     */
    public function listen(string $eventType, callable $callable)
    {
        $this->listenerProvider->addListener($eventType, $callable);
    }

    /**
     * The main event dispatcher.
     *
     * @return \Core\Events\EventDispatcher
     */
    public function eventDispatcher()
    {
        return $this->dispatcher;
    }
}
