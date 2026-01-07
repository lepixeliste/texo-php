<?php

namespace Core\Socket;

use Core\Context;
use Core\Pdo\WebEvent;
use Core\StdObject;
use Socket;

/**
 * WebClient
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class WebClient
{
    const MAX_DATA_LENGTH = 5000;

    /** @var \Core\Socket\WebServer */
    private $server;

    /** @var \Core\Context */
    private $context;

    /** @var \Socket */
    private $socket;

    /** @var bool */
    private $connected;

    public function __construct(WebServer $server, Context $context)
    {
        $this->server = $server;
        $this->context = $context;
        $this->connected = false;
    }

    public function write(string $message)
    {
        if (!$this->isConnected()) {
            return false;
        }
        $data = chr(129) . chr(strlen($message)) . $message;
        return socket_write($this->socket, $data);
    }

    public function connect()
    {
        if ($this->isConnected()) {
            return true;
        }

        $this->socket = socket_accept($this->server->getSocket());

        $headers = [];

        $server_protocol = $_SERVER['SERVER_PROTOCOL'] ?? '1.1';
        $protocol_version = preg_replace('/[^0-9.]/', '', $server_protocol);
        $headers[] = "HTTP/$protocol_version 101 Switching Protocols";

        $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $headers[] = "Host: $host";
        $headers[] = "Upgrade: websocket";
        $headers[] = "Connection: Upgrade";
        $headers[] = "Sec-WebSocket-Version: 13";

        $request = socket_read($this->socket, static::MAX_DATA_LENGTH);
        preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $request, $matches);
        if (count($matches) < 2) {
            return false;
        }
        $sha1 = sha1($matches[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11');
        $key = base64_encode(pack('H*', $sha1));
        $headers[] = "Sec-WebSocket-Accept: $key";

        $data = implode("\r\n", $headers) . "\r\n\r\n";
        $w = @socket_write($this->socket, $data, strlen($data));
        $this->connected = $w !== false;
        return $w !== false;
    }

    public function isConnected()
    {
        return $this->connected;
    }

    public function loop()
    {
        while (true) {
            if (!$this->isConnected()) {
                break;
            }

            $r = socket_read($this->socket, static::MAX_DATA_LENGTH);
            if ($r !== false) {
                $emitter = new StdObject();
                $emitter->message = $this->unmask($r);
                $event = new WebEvent('Message', $emitter);
                $this->context->eventDispatcher()->dispatch($event);
            }
        }
    }

    public function close()
    {
        if ($this->socket instanceof Socket) {
            socket_close($this->socket);
        } elseif (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
        $this->connected = false;
    }

    private function unmask($text)
    {
        $length = ord($text[1]) & 127;
        if ($length == 126) {
            $masks = substr($text, 4, 4);
            $data = substr($text, 8);
        } elseif ($length == 127) {
            $masks = substr($text, 10, 4);
            $data = substr($text, 14);
        } else {
            $masks = substr($text, 2, 4);
            $data = substr($text, 6);
        }
        $text = '';
        for ($i = 0; $i < strlen($data); ++$i) {
            $text .= $data[$i] ^ $masks[$i % 4];
        }
        return $text;
    }
}
