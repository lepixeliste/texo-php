<?php

namespace Core;

use Core\Context;
use Core\ControllerException;

/**
 * Abstract controller class that can handle HTTP request.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

abstract class Controller
{
    /**
     * The service context.
     *
     * @var \Core\Context
     */
    protected $context;

    /**
     * @param \Core\Context $context
     * @return void
     * @throws \Core\ControllerException
     */
    public function __construct(Context $context)
    {
        if (!isset($context) || !($context instanceof context)) {
            throw new ControllerException(ControllerException::NO_CONTEXT);
        }
        $this->context = $context;
    }

    /**
     * The service context.
     *
     * @return \Core\Context
     */
    public function context()
    {
        return $this->context;
    }

    /**
     * The context database.
     *
     * @return \Core\Pdo\Db
     */
    public function db()
    {
        return $this->context->db();
    }

    /**
     * The context event dispatcher.
     *
     * @return \Core\Events\EventDispatcher
     */
    public function eventDispatcher()
    {
        return $this->context->eventDispatcher();
    }
}
