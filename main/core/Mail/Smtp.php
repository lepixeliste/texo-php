<?php

namespace Core\Mail;

use Core\Cli\Printer;
use Exception;

/**
 * Implements RFC 821 SMTP commands and provides some utility methods for sending mail to an SMTP server.
 * 
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class Smtp
{
    /**
     * The SMTP port to use if one is not specified.
     *
     * @var int
     */
    const DEFAULT_PORT = 25;

    /**
     * The maximum line length allowed by RFC 5321 section 4.5.3.1.6,
     * *excluding* a trailing CRLF break.
     *
     * @var int
     * @see https://tools.ietf.org/html/rfc5321#section-4.5.3.1.6
     */
    const MAX_LINE_LENGTH = 998;

    /**
     * The maximum line length allowed for replies in RFC 5321 section 4.5.3.1.5,
     * *including* a trailing CRLF line break.
     * 
     * @var int
     * @see https://tools.ietf.org/html/rfc5321#section-4.5.3.1.5
     */
    const MAX_REPLY_LENGTH = 8192; // 512;

    /**
     * SMTP line break constant.
     *
     * @var string
     */
    const LE = "\r\n";

    /**
     * The timeout value for connection, in seconds.
     * Default of 5 minutes (300sec) is from RFC2821 section 4.5.3.2.
     * This needs to be quite high to function correctly with hosts using greetdelay as an anti-spam measure.
     *
     * @var int
     * @see http://tools.ietf.org/html/rfc2821#section-4.5.3.2
     */
    public $timeout = 300;

    /**
     * How long to wait for commands to complete, in seconds.
     * Default of 5 minutes (300sec) is from RFC2821 section 4.5.3.2.
     *
     * @var int
     */
    public $limit = 300;

    /**
     * The most recent reply received from the server.
     *
     * @var string
     */
    protected $lastReply = '';

    /**
     * The reply the server sent to us for HELO.
     * If null, no HELO string has yet been received.
     *
     * @var string|null
     */
    protected $heloReply;

    /**
     * The set of SMTP extensions sent in reply to EHLO command.
     * Indexes of the array are extension names.
     * Value at index 'HELO' or 'EHLO' (according to command that was sent)
     * represents the server name. In case of HELO it is the only element of the array.
     * Other values can be boolean TRUE or an array containing extension options.
     * If null, no HELO/EHLO string has yet been received.
     *
     * @var array|null
     */
    protected $extensions;

    /** @var resource */
    protected $socket;

    /** @var string */
    protected $host;

    /** @var string|int */
    protected $port;

    /** @var \Core\Mail\MailException[] */
    private $errors = [];

    /** @var \Core\Cli\Printer */
    private $printer;

    /**
     * @param  string|null $host The SMTP host
     * @param  int|null $port The SMTP port
     * @param  array $options Optional key-value pair for setup 
     * @return void
     */
    public function __construct($host, $port = null, $options = [])
    {
        $this->host = $host;
        $this->port = isset($port) ? intval($port) : Smtp::DEFAULT_PORT;
        $this->timeout = intval(get_value('timeout', $options, 300));
    }

    /**
     * @return void
     * @see quit
     */
    public function __destruct()
    {
        $this->quit();
    }

    /**
     * Connects to the STMP server.
     * 
     * @param  string $scheme
     * @return self
     */
    public function connect($scheme = 'ssl')
    {
        if (isset($this->socket)) {
            return $this;
        }

        $this->errors = [];

        $error_code = 0;
        $error_message = '';

        try {
            $socket_context = stream_context_create();
            // stream_context_set_option($socket_context, 'ssl', 'verify_peer', false);
            // stream_context_set_option($socket_context, 'ssl', 'verify_peer_name', false);

            set_error_handler([$this, 'errorHandler']);
            $socket = stream_socket_client(
                $scheme . '://' . $this->host . ':' . $this->port,
                $error_code,
                $error_message,
                $this->timeout,
                STREAM_CLIENT_CONNECT,
                $socket_context
            );
            restore_error_handler();

            if (!is_resource($socket)) {
                if ($this->printer instanceof Printer) {
                    $this->printer->out("{red}SMTP connection failed{nc} => {$this->host}:{$this->port}");
                }
                $this->socket = null;
                if (count($this->errors) > 0) {
                    throw $this->errors[count($this->errors) - 1];
                }
            }

            // Windows does not have support for this timeout function
            if (strpos(PHP_OS, 'WIN') !== 0) {
                $max = (int)ini_get('max_execution_time');
                if (0 !== $max && $this->timeout > $max && strpos(ini_get('disable_functions'), 'set_time_limit') === false) {
                    @set_time_limit($this->timeout);
                }
                stream_set_timeout($socket, $this->timeout, 0);
            }

            $this->socket = $socket;
            if ($this->printer instanceof Printer) {
                $this->printer->out("{green}SMTP connection successful{nc} => {$this->host}:{$this->port}");
            }
        } catch (Exception $e) {
            $this->errorHandler($error_code, $error_message, $e->getFile(), $e->getLine());
        }

        return $this;
    }

    /**
     * Pings the STMP server and checks if it responds accordingly.
     * 
     * @param  \Core\Cli\Printer $printer
     * @return bool
     */
    public function ping(Printer $printer)
    {
        $this->printer = $printer;
        $this->connect();

        if (!is_resource($this->socket)) {
            return false;
        }

        $this->hello();

        $user = getenv('SMTP_USER');
        $pass = getenv('SMTP_PASS');
        if (isset($user, $pass)) {
            $this->authenticate($user, $pass);
        }
        $this->quit();

        return true;
    }

    /**
     * Gets mail exceptions, if any.
     * 
     * @return \Core\Mail\MailException[]
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * Gets the latest error, if any.
     * 
     * @return \Core\Mail\MailException|null
     */
    public function lastError()
    {
        return count($this->errors) > 0 ? $this->errors[count($this->errors) - 1] : null;
    }

    /**
     * Checks if it is connected to the socket.
     * 
     * @return bool
     */
    public function connected()
    {
        if (is_resource($this->socket)) {
            $sock_status = stream_get_meta_data($this->socket);
            if ($sock_status['eof']) {
                // The socket is valid but we are not connected
                if ($this->printer instanceof Printer) {
                    $this->printer->out('{bgyellow;bold} NOTICE {nc} EOF caught while checking if connected');
                }
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * Perform SMTP authentication.
     * Must be run after hello().
     *
     * @param string $username The user name
     * @param string $password The password
     * @param string $authtype The auth type (CRAM-MD5, PLAIN, LOGIN)
     * @return self
     * @see hello
     */
    public function authenticate(
        $username,
        $password,
        $authtype = null
    ) {
        if (!$this->extensions) {
            if ($this->printer instanceof Printer) {
                $this->printer->out('{bgred;bold} ERROR {nc} Authentication is not allowed before HELO/EHLO');
            }
            $this->errorHandler(0, 'Authentication is not allowed before HELO/EHLO');
            return false;
        }

        if (array_key_exists('EHLO', $this->extensions)) {
            // SMTP extensions are available; try to find a proper authentication method
            if (!array_key_exists('AUTH', $this->extensions)) {
                if ($this->printer instanceof Printer) {
                    $this->printer->out('{bgred;bold} ERROR {nc} Authentication is not allowed at this stage');
                }
                $this->errorHandler(0, 'Authentication is not allowed at this stage');
                // 'at this stage' means that auth may be allowed after the stage changes
                // e.g. after STARTTLS

                return false;
            }

            if ($this->printer instanceof Printer) {
                $this->printer
                    ->out('{bgyellow;bold} NOTICE {nc} Auth method requested: ' . ($authtype ?: 'UNSPECIFIED'))
                    ->out('{bgyellow;bold} NOTICE {nc} Auth methods available on the server: ' . implode(',', $this->extensions['AUTH']));
            }
            // If we have requested a specific auth type, check the server supports it before trying others
            if (null !== $authtype && !in_array($authtype, $this->extensions['AUTH'], true)) {
                if ($this->printer instanceof Printer) {
                    $this->printer->out('{bgred;bold} ERROR {nc} Requested auth method not available: ' . $authtype);
                }
                $this->errorHandler(0, 'Requested auth method not available: ' . $authtype);
                $authtype = null;
            }

            if (empty($authtype)) {
                // If no auth mechanism is specified, attempt to use these, in this order
                // Try CRAM-MD5 first as it's more secure than the others
                foreach (['CRAM-MD5', 'LOGIN', 'PLAIN'] as $method) {
                    if (in_array($method, $this->extensions['AUTH'], true)) {
                        $authtype = $method;
                        break;
                    }
                }
                if (empty($authtype)) {
                    if ($this->printer instanceof Printer) {
                        $this->printer->out('{bgred;bold} ERROR {nc} No supported authentication methods found');
                    }
                    $this->errorHandler(0, 'No supported authentication methods found');
                    return false;
                }
                if ($this->printer instanceof Printer) {
                    $this->printer->out('{bgyellow;bold} NOTICE {nc} Auth method selected: ' . $authtype);
                }
            }

            if (!in_array($authtype, $this->extensions['AUTH'], true)) {
                if ($this->printer instanceof Printer) {
                    $this->printer->out("{bgred;bold} ERROR {nc} The requested authentication method `{italic}$authtype{nc}` is not supported by the server");
                }
                $this->errorHandler(0, "The requested authentication method `$authtype` is not supported by the server");
                return false;
            }
        } elseif (empty($authtype)) {
            $authtype = 'LOGIN';
        }

        // Start authentication
        if (!$this->sendCommand("AUTH $authtype")) {
            return false;
        }

        switch ($authtype) {
            case 'PLAIN':
                // Send encoded username and password
                if (
                    // Format from https://tools.ietf.org/html/rfc4616#section-2
                    // We skip the first field (it's forgery), so the string starts with a null byte
                    !$this->sendCommand(base64_encode("\0" . $username . "\0" . $password))
                ) {
                    return false;
                }
                break;
            case 'LOGIN':
                if (!$this->sendCommand(base64_encode($username))) {
                    return false;
                }
                if (!$this->sendCommand(base64_encode($password))) {
                    return false;
                }
                break;
            case 'CRAM-MD5':
                // Get the challenge
                $challenge = base64_decode(substr($this->lastReply, 4));
                // Build the response
                $response = $username . ' ' . $this->hmac($challenge, $password);
                // Send encoded credentials
                return $this->sendCommand(base64_encode($response));
            default:
                if ($this->printer instanceof Printer) {
                    $this->printer->out("{bgred;bold} ERROR {nc} Authentication method `{italic}$authtype{nc}` is not supported");
                }
                $this->errorHandler(0, "Authentication method `$authtype` is not supported");
                return false;
        }

        return true;
    }

    /**
     * Close the socket and clean up the state of the class.
     * Don't use this function without first trying to use QUIT.
     *
     * @return bool
     * @see quit
     */
    private function close()
    {
        $this->extensions = null;
        $this->heloReply = null;
        if (is_resource($this->socket)) {
            fclose($this->socket);
            $this->socket = null;
            if ($this->printer instanceof Printer) {
                $this->printer->out("{yellow}SMTP connection closed{nc} => {$this->host}:{$this->port}");
            }
        }
        return true;
    }

    /**
     * Send an SMTP QUIT command.
     * Closes the socket if there is no error or the $close_on_error argument is true.
     * Implements from RFC 821: QUIT <CRLF>.
     *
     * @param bool $close_on_error Should the connection close if an error occurs?
     * @return self
     */
    public function quit($close_on_error = true)
    {
        if ($this->connected()) {
            $this->sendCommand('QUIT');
        }
        if ($close_on_error) {
            $this->close();
        }
        return $this;
    }

    /**
     * Send an SMTP HELO or EHLO command.
     * Used to identify the sending server to the receiving server.
     * This makes sure that client and server are in a known state.
     * Implements RFC 821: HELO <SP> <domain> <CRLF>
     * and RFC 2821 EHLO.
     *
     * @param string $host The host name or IP to connect to
     * @return self
     */
    public function hello()
    {
        // Try extended EHLO first (RFC 2821)
        $this->sendHello('EHLO');
        $code = intval(substr(is_string($this->heloReply) ? $this->heloReply : '', 0, 3));
        // Some servers shut down the SMTP service here (RFC 5321)
        if ($code === 250 || $code === 421) {
            return false;
        }

        return $this->sendHello('HELO');
    }

    /**
     * Send an SMTP HELO or EHLO command.
     * Low-level implementation used by hello().
     *
     * @param string $hello The HELO string
     * @return bool
     * @see hello
     */
    protected function sendHello($hello)
    {
        $sent = $this->sendCommand($hello . ' ' . $this->host);
        $this->heloReply = $this->lastReply;
        if (!$sent) {
            $this->extensions = null;
            return false;
        }

        $this->extensions = [];
        $lines = explode("\n", $this->heloReply);

        foreach ($lines as $n => $s) {
            // First 4 chars contain response code followed by - or space
            $s = trim(substr($s, 4));
            if (empty($s)) {
                continue;
            }
            $fields = explode(' ', $s);
            if (!empty($fields)) {
                if (!$n) {
                    $name = $hello;
                    $fields = $fields[0];
                } else {
                    $name = array_shift($fields);
                    switch ($name) {
                        case 'SIZE':
                            $fields = ($fields ? $fields[0] : 0);
                            break;
                        case 'AUTH':
                            if (!is_array($fields)) {
                                $fields = [];
                            }
                            break;
                        default:
                            $fields = true;
                    }
                }
                $this->extensions[$name] = $fields;
            }
        }

        return true;
    }

    /**
     * Send an SMTP MAIL command.
     * Starts a mail transaction from the email address specified in
     * $from. Returns true if successful or false otherwise. If True
     * the mail transaction is started and then one or more recipient
     * commands may be called followed by a data command.
     * Implements RFC 821: MAIL <SP> FROM:<reverse-path> <CRLF>.
     *
     * @param string $sender Source address of this message
     * @return bool
     */
    public function from($sender)
    {
        return $this->sendCommand('MAIL FROM:<' . $sender . '>');
    }

    /**
     * Send an SMTP RCPT command.
     * Sets the TO argument to $toaddr.
     * Gets true if the recipient was accepted false if it was rejected.
     * Implements from RFC 821: RCPT <SP> TO:<forward-path> <CRLF>.
     *
     * @param string $recipient  The address the message is being sent to
     * @param string $dsn        Comma separated list of DSN notifications. NEVER, SUCCESS, FAILURE
     *                           or DELAY. If you specify NEVER all other notifications are ignored.
     * @return bool
     */
    public function to($recipient, $dsn = '')
    {
        if (empty($dsn)) {
            $rcpt = 'RCPT TO:<' . $recipient . '>';
            return $this->sendCommand($rcpt);
        }

        $dsn = strtoupper($dsn);
        $notify = [];

        if (strpos($dsn, 'NEVER') !== false) {
            $notify[] = 'NEVER';
        } else {
            foreach (['SUCCESS', 'FAILURE', 'DELAY'] as $value) {
                if (strpos($dsn, $value) !== false) {
                    $notify[] = $value;
                }
            }
        }

        $rcpt = 'RCPT TO:<' . $recipient . '> NOTIFY=' . implode(',', $notify);
        return $this->sendCommand($rcpt);
    }

    /**
     * Send an SMTP DATA command.
     * Issues a data command and sends the msg_data to the server,
     * finalizing the mail transaction. $msg_data is the message
     * that is to be send with the headers. Each header needs to be
     * on a single line followed by a <CRLF> with the message headers
     * and the message body being separated by an additional <CRLF>.
     * Implements RFC 821: DATA <CRLF>.
     *
     * @param string $message Message data to send
     * @return bool
     */
    public function data($message)
    {
        $this->sendCommand('DATA');
        $this->sendClient($message . static::LE);
        return $this->sendCommand('.');
    }

    /**
     * Send a command to an SMTP server and check its return code.
     *
     * @param string    $command    The actual command to send
     * @return bool True on success
     */
    protected function sendCommand($command)
    {
        $commands = explode(' ', $command);
        $command_name = count($commands) > 0 ? strtoupper($commands[0]) : 'NULL';
        $connected = $this->connected();

        if (!$connected) {
            if ($this->printer instanceof Printer) {
                $this->printer->out("{bgred;bold} ERROR {nc} called `{italic}$command_name{nc}` without being connected");
            }
            return false;
        }

        // Reject line breaks in all commands
        if ((strpos($command, "\n") !== false) || (strpos($command, "\r") !== false)) {
            $this->errorHandler(0, "Command `$command_name` contained line breaks");
            return false;
        }

        $this->sendClient($command . static::LE);
        if ($this->printer instanceof Printer) {
            $this->printer->out('{bgblue;bold} C {nc} ' . trim($command));
        }

        $this->lastReply = $this->readServer();

        // Fetch SMTP code and possible error code explanation
        $matches = [];

        $code = 0;
        $code_message = 'Undefined';

        if (preg_match('/^([\d]{3})[ -](?:([\d]\\.[\d]\\.[\d]{1,2}) )?/', $this->lastReply, $matches)) {
            $code = (int) $matches[1];
            $code_ex = (count($matches) > 2 ? $matches[2] : null);
            //Cut off error code from each response line
            $code_message = trim(preg_replace(
                "/{$code}[ -]" .
                    (is_string($code_ex) ? str_replace('.', '\\.', $code_ex) . ' ' : '') . '/m',
                '',
                $this->lastReply
            ));
        } else {
            // Fall back to simple parsing if regex fails
            $code = (int) substr($this->lastReply, 0, 3);
            $code_message = trim(substr($this->lastReply, 4));
        }

        $replies = array_filter(explode(static::LE, $this->lastReply), function ($line) {
            return !empty($line);
        });
        foreach ($replies as $reply) {
            if ($this->printer instanceof Printer) {
                $this->printer->out('{bgpurple;bold} S {nc} ' . trim($reply));
            }
        }

        if ($code > 399) {
            if ($this->printer instanceof Printer) {
                $this->printer->out("{bgred;bold} ERROR {nc} [{yellow}$code{nc}] command `{italic}$command_name{nc}` failed ($code_message)");
            }
            return false;
        }

        return true;
    }

    /**
     * Send raw data to the server.
     *
     * @param string $data    The data to send
     * @return int|bool The number of bytes sent to the server or false on error
     */
    public function sendClient($data)
    {
        set_error_handler([$this, 'errorHandler']);
        $result = fwrite($this->socket, $data);
        restore_error_handler();

        return $result;
    }

    /**
     * Read the SMTP server's response.
     * Either before eof or socket timeout occurs on the operation.
     * With SMTP we can tell if we have more lines to read if the
     * 4th character is '-' symbol. If it is a space then we don't
     * need to read anything else.
     *
     * @return string
     */
    protected function readServer()
    {
        if (!is_resource($this->socket)) {
            return '';
        }

        $data = '';
        $endtime = 0;
        stream_set_timeout($this->socket, $this->timeout);
        if ($this->limit > 0) {
            $endtime = time() + $this->limit;
        }

        $selR = [$this->socket];
        $selW = null;
        while (is_resource($this->socket) && !feof($this->socket)) {
            set_error_handler([$this, 'errorHandler']);
            $n = stream_select($selR, $selW, $selW, $this->limit);
            restore_error_handler();

            if ($n === false) {
                break;
            }

            if (!$n) {
                if ($this->printer instanceof Printer) {
                    $this->printer->out('{bgred;bold} ERROR {nc} select timed-out in {yellow}' . $this->limit . '{nc} sec');
                }
                $this->errorHandler(0, 'Timed-out in ' . $this->limit . 'sec');
                break;
            }

            // Deliberate noise suppression - errors are handled afterwards
            $str = fread($this->socket, self::MAX_REPLY_LENGTH);
            $data .= $str;
            // If response is only 3 chars (not valid, but RFC5321 S4.2 says it must be handled),
            // or 4th character is a space or a line break char, we are done reading, break the loop.
            // String array access is a significant micro-optimisation over strlen
            if (!isset($str[3]) || $str[3] === ' ' || $str[3] === "\r" || $str[3] === "\n") {
                break;
            }
            // Timed-out? Log and break
            $info = stream_get_meta_data($this->socket);
            if ($info['timed_out']) {
                if ($this->printer instanceof Printer) {
                    $this->printer->out('{bgred;bold} ERROR {nc} stream timed-out ({yellow}' . $this->timeout . '{nc} sec)');
                }
                $this->errorHandler(0, 'stream timed-out (' . $this->timeout . ' sec)');
                break;
            }
            // Now check if reads took too long
            if ($endtime && time() > $endtime) {
                if ($this->printer instanceof Printer) {
                    $this->printer->out('{bgred;bold} ERROR {nc} time limit reached ({yellow}' . $this->limit . '{nc} sec)');
                }
                $this->errorHandler(0, 'Time limit reached ({yellow}' . $this->limit . '{nc} sec)');
                break;
            }
        }

        return $data;
    }

    /**
     * Calculate an MD5 HMAC hash.
     * Works like hash_hmac('md5', $data, $key)
     * in case that function is not available.
     *
     * @param string $data The data to hash
     * @param string $key  The key to hash with
     * @return string
     */
    protected function hmac($data, $key)
    {
        if (function_exists('hash_hmac')) {
            return hash_hmac('md5', $data, $key);
        }

        // The following borrowed from
        // http://php.net/manual/en/function.mhash.php#27225

        // RFC 2104 HMAC implementation for php.
        // Creates an md5 HMAC.
        // Eliminates the need to install mhash to compute a HMAC
        // by Lance Rushing

        $bytelen = 64; // byte length for md5
        if (strlen($key) > $bytelen) {
            $key = pack('H*', md5($key));
        }
        $key = str_pad($key, $bytelen, chr(0x00));
        $ipad = str_pad('', $bytelen, chr(0x36));
        $opad = str_pad('', $bytelen, chr(0x5c));
        $k_ipad = $key ^ $ipad;
        $k_opad = $key ^ $opad;

        return md5($k_opad . pack('H*', md5($k_ipad . $data)));
    }

    /**
     * Handles any exceptions.
     * 
     * @param int $code The Exception code
     * @param string $message The Exception message
     * @param string $file The Exception file
     * @param int $line The Exception line
     * @return void
     */
    protected function errorHandler($code, $message, $file = '', $line = -1)
    {
        if ($this->printer instanceof Printer) {
            $this->printer->out("{bgred;bold} ERROR {nc} [{yellow}$code{nc}] $message {italic}at{nc} `{italic}$file{nc}` ({italic}$line{nc})");
        }
        $e = new MailException($message, $code);
        if (!empty($file)) {
            $e->setFile($file);
        }
        if ($line > -1) {
            $e->setLine($line);
        }
        $this->errors[] = $e;
    }
}
