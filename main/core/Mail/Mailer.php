<?php

namespace Core\Mail;

/**
 * PHP email creation and transport class.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class Mailer
{
    /** @var string|null */
    protected $lastError;

    /** 
     * RFC821 SMTP email transport class.
     * 
     * @var \Core\Mail\Smtp 
     */
    protected $smtp;

    /**
     * Transports the message using the SMTP server.
     * 
     * @param  string|null $host The SMTP host, or the default server from .env file if not set
     * @param  int|null $port The SMTP port, or the default port from .env file if not set
     * @return self
     */
    public function viaSmtp($host = null, $port = null)
    {
        $this->smtp = new Smtp(isset($host) ? $host : getenv('SMTP_HOST'), is_numeric($port) ? $port : getenv('SMTP_PORT'));
        return $this;
    }

    /**
     * Gets the latest error, if any.
     * 
     * @return string|null
     */
    public function error()
    {
        return $this->lastError;
    }

    /**
     * Sends the message.
     * 
     * @return bool
     * @throws \Core\Mail\MailException
     */
    public function send(MailMessage $mail)
    {
        $this->lastError = null;

        if (isset($this->smtp)) {
            $this->smtp
                ->connect()
                ->hello();

            if (!$this->smtp->authenticate(getenv('SMTP_USER'), getenv('SMTP_PASS'))) {
                return false;
            }

            $this->smtp->from($mail->getSmtpSender());
            $recipients = $mail->getSmtpRecipients();
            foreach ($recipients as $recipient) {
                $this->smtp->to($recipient);
            }
            $this->smtp->data($mail->getSmtpBody());
            $this->smtp->quit();
            if (null !== ($e = $this->smtp->lastError())) {
                throw $e;
            }
            return true;
        }

        $sent = mail(
            $mail->getRecipients(),
            $mail->getSubject(),
            $mail->getBody(),
            $mail->getHeaders()
        );
        if (!$sent) {
            $error = error_get_last();
            if (isset($error)) {
                $e = new MailException(get_value('message', $error, ''), intval(get_value('type', $error, '0')));
                $this->lastError = $e
                    ->setFile(get_value('file', $error, ''))
                    ->setLine(get_value('line', $error, '0'));
                throw $e;
            }
        }
        return $sent;
    }
}
