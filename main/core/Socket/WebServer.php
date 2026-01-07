<?php

namespace Core\Socket;

use Socket;

/**
 * WebServer
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class WebServer
{
    /** @var \Socket */
    private $socket;

    /** @var string */
    private $address;

    /** @var int */
    private $port;

    /** @var string|boolean */
    private $error = false;

    public function __construct($address = '0.0.0.0', $port = 12345)
    {
        $this->address = $address;
        $this->port = intval($port);
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        @socket_bind($this->socket, $address, $port);
        $error_code = socket_last_error($this->socket);
        if ($error_code > 0) {
            $this->error = socket_strerror($error_code);
        }
    }

    public function error()
    {
        return $this->error;
    }

    public function getSocket()
    {
        return $this->socket;
    }

    public function getName()
    {
        return $this->address . ':' . $this->port;
    }

    public function listen()
    {
        if (!($this->socket instanceof Socket)) {
            return false;
        }
        $success = socket_listen($this->socket, SOMAXCONN);
        return $success;
    }

    public function close()
    {
        if (!($this->socket instanceof Socket)) {
            return;
        }
        socket_shutdown($this->socket, 2);
        socket_close($this->socket);
        $this->socket = null;
    }
}
