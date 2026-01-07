<?php

namespace Core\Mail;

use Core\View;
use Serializable;

/**
 * A RFC 821 compliant mail transport message class.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class MailMessage implements Serializable
{
    /** To: */
    const FIELD_TO = 'To';
    /** Cc: */
    const FIELD_CC = 'Cc';
    /** Bcc: */
    const FIELD_BCC = 'Bcc';

    /**
     * [Email, Name] for `From:` header string.
     *
     * @var string[]
     */
    protected $sender = [];

    /**
     * [Email, Name] for `Reply-To:` header string.
     *
     * @var string[]
     */
    protected $reply = [];

    /**
     * The subject mail.
     *
     * @var string
     */
    protected $subject = '';

    /**
     * The plain text message string.
     *
     * @var string
     */
    protected $message = '';

    /**
     * The ISO-8859-1 html string.
     *
     * @var string
     */
    protected $html = '';

    /**
     * The RFC 2822 compliant recipients.
     *
     * @var array
     */
    protected $recipients = [];

    /**
     * Email priority.
     * Options: null (default), 1 = High, 3 = Normal, 5 = low.
     * When null, the header is not set at all.
     *
     * @var int|null
     */
    protected $priority;

    /**
     * The MIME boundary parameter.
     *
     * @var string|null
     */
    protected $boundary;

    /**
     * @return void
     */
    public function __construct(string $subject, $email = null, $optional_name = null)
    {
        $this->boundary = uniqid('b' . time(), true);
        $this->subject = $subject;
        $this->setFrom(is_string($email) ? $email : '', is_string($optional_name) ? $optional_name : '');
    }

    /**
     * Sets the mail subject.
     * 
     * @param  string $subject
     * @return self
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Sets the mail priority.
     * 
     * @param  int $priority Between 1 and 5
     * @return self
     */
    public function setPriority($priority)
    {
        $p = intval($priority);
        $this->priority = $p > 0 && $p < 6 ? $p : null;
        return $this;
    }

    /**
     * Gets the mail subject.
     * 
     * @return string
     */
    public function getSubject()
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $this->subject);
    }

    /**
     * Sets the mail sender.
     * 
     * @param  string $email
     * @param  string|null $optional_name
     * @return self
     */
    public function setFrom($email, $optional_name = null)
    {
        $email = filter_var($email, FILTER_VALIDATE_EMAIL, FILTER_NULL_ON_FAILURE);
        $this->sender = [trim(is_string($email) ? $email : ''), trim(is_string($optional_name) ? $optional_name : '')];

        return $this;
    }

    /**
     * Gets the formatted mail sender.
     * 
     * @return string
     */
    public function getSender()
    {
        return $this->formatToRFC($this->sender);
    }

    /**
     * Gets the mail sender for STMP.
     * 
     * @return string
     */
    public function getSmtpSender()
    {
        return count($this->sender) > 0 ? $this->sender[0] : '';
    }

    /**
     * Sets the `Reply-To:` header string.
     * 
     * @param  string $email
     * @param  string|null $optional_name
     * @return self
     */
    public function replyTo($email, $optional_name = null)
    {
        $email = filter_var($email, FILTER_VALIDATE_EMAIL, FILTER_NULL_ON_FAILURE);
        $this->reply = [trim($email), trim($optional_name)];

        return $this;
    }

    /**
     * Adds a recipient.
     * 
     * @param  \Core\Mail\MailRecipient $recipient
     * @param  string $field e.g.: To, Cc, Bcc
     * @return self
     */
    public function addRecipient(MailRecipient $recipient, $field = MailMessage::FIELD_TO)
    {
        if (!($recipient instanceof MailRecipient) || !$recipient->isValid()) {
            throw new MailException('The recipient is not valid.');
        }

        if (!isset($this->recipients[$field])) {
            $this->recipients[$field] = [];
        }

        $count = count(array_filter($this->recipients[$field], function ($item) use ($recipient) {
            return $item->format() === $recipient->format();
        }));
        if ($count < 1) {
            $this->recipients[$field][] = $recipient;
        }

        return $this;
    }

    /**
     * Adds recipients.
     * 
     * @param  \Core\Mail\MailRecipient[] $recipients
     * @param  string $field e.g.: To, Cc, Bcc
     * @return self
     */
    public function addRecipients(array $recipients, $field = MailMessage::FIELD_TO)
    {
        foreach ($recipients as $recipient) {
            $this->addRecipient($recipient, $field);
        }
        return $this;
    }

    /**
     * Gets formatted recipients by field.
     * 
     * @param  string $field e.g.: To, Cc, Bcc
     * @return string
     */
    public function getRecipients($field = MailMessage::FIELD_TO)
    {
        $recipients = isset($this->recipients[$field]) ? implode(', ', array_map(function ($item) {
            return $item->format();
        }, $this->recipients[$field])) : '';
        return !empty($recipients) ? $recipients : '';
    }

    /**
     * Gets formatted recipients for STMP.
     * 
     * @return string[]
     */
    public function getSmtpRecipients()
    {
        $emails = [];
        $fields = [static::FIELD_TO, static::FIELD_CC, static::FIELD_BCC];
        foreach ($fields as $field) {
            if (!isset($this->recipients[$field])) {
                continue;
            }
            foreach ($this->recipients[$field] as $recipient) {
                $emails[] = $recipient->email;
            }
        }
        return $emails;
    }

    /**
     * Sets the body message.
     * 
     * @param  string $message
     * @return self
     */
    public function setMessage($message)
    {
        preg_match('/<body\s*.*>.*<\/body>/s', $message, $matches);
        $raw_body = isset($matches[0]) ? $matches[0] : '';
        $raw_text = preg_replace('/\v{2,}/', "\r\n", strip_tags(preg_replace('/<\/h1>|<\/h2>|<\/h3>|<\/h4>|<\/h5>|<\/h6>|<\/p>|<br\/>/', "\r\n", $raw_body)));
        $this->message = trim(wordwrap(str_replace("\n.", "\n..", $raw_text), 76, "\r\n"));

        return $this;
    }

    /**
     * Gets the body message.
     * 
     * @return string
     */
    public function getMessage()
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $this->message);
    }

    /**
     * Sets the body HTML message.
     * 
     * @param  string $html
     * @return self
     */
    public function setHtml($html)
    {
        $this->html = wordwrap($html, 76, "\r\n");
        return $this->setMessage($html);
    }

    /**
     * Gets the body HTML message.
     * 
     * @return string
     */
    public function getHtml()
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $this->html);
    }

    /**
     * Gets the message charset.
     * 
     * @return string
     */
    public function getCharset()
    {
        // return getenv('APP_CHARSET');
        return 'ISO-8859-1';
    }

    /**
     * Gets the message body.
     * 
     * @return string
     */
    public function getBody()
    {
        if (strlen($this->html)) {
            $hypens = '--';
            $charset = $this->getCharset();
            $body = [];
            $body[] = "$hypens{$this->boundary}";
            $body[] = "Content-Type: text/plain; charset=$charset";
            $body[] = 'Content-Transfer-Encoding: base64';
            $body[] = 'Content-Disposition: inline';
            $body[] = '';
            $body[] = base64_encode($this->getMessage());
            $body[] = "$hypens{$this->boundary}";
            $body[] = "Content-Type: text/html; charset=$charset";
            $body[] = 'Content-Transfer-Encoding: base64';
            $body[] = 'Content-Disposition: inline';
            $body[] = '';
            $body[] = base64_encode($this->getHtml());
            $body[] = "$hypens{$this->boundary}$hypens";
            return implode("\r\n", $body);
        }
        return $this->message;
    }

    /**
     * Gets the message body for SMTP.
     * 
     * @return string
     */
    public function getSmtpBody()
    {
        return implode("\r\n", [$this->getHeaders(), '', $this->getBody()]);
    }

    /**
     * Sets the body HTML message with a view.
     * 
     * @param  \Core\View $view
     * @return self
     */
    public function setView(View $view)
    {
        if (!($view instanceof View)) {
            throw new MailException('View is not valid.');
        }

        $view->setContext('subject', $this->subject);
        return $this->setHtml($view->render());
    }

    /**
     * Gets message headers.
     * 
     * @return string
     */
    public function getHeaders()
    {
        $sender = $this->getSender();
        $headers = [
            'Date: ' . date('r'),
            'From: ' . $sender,
            'Reply-To: ' . (empty($this->reply) ? $sender : $this->formatToRFC($this->reply)),
        ];
        $recipients = '';
        if (isset($this->recipients[static::FIELD_TO])) {
            $recipients = implode(', ', $this->recipients[static::FIELD_TO]);
            $headers[] = static::FIELD_TO . ': ' . $recipients;
        }

        $headers[] = 'Subject: ' . $this->getSubject();
        $headers[] = 'X-Mailer: ' . 'PHP/' . phpversion();
        if (isset($this->priority)) {
            $headers[] = 'X-Priority: ' . $this->priority;
        }

        $arbitrary = @base_convert(microtime(), 10, 36);
        $hash_send = md5($sender . $recipients);
        $host_name = getenv('SMTP_HOST');
        $headers[] = "Message-ID: <{$arbitrary}.{$hash_send}@{$host_name}>";

        $headers[] = 'MIME-Version: 1.0';
        $headers[] = is_string($this->html) && strlen($this->html) > 0 ?
            "Content-Type: multipart/alternative; boundary=\"{$this->boundary}\""
            : 'Content-type: text/plain; charset=' . $this->getCharset();

        return implode("\r\n", $headers);
    }

    /**
     * String representation of the message. 
     * 
     * @return string
     */
    public function serialize(): string
    {
        $encode = json_encode($this->__serialize());
        return $encode !== false ? $encode : '';
    }

    /**
     * Constructs the message from array. 
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
     * Data representation of the message. 
     * 
     * @return array
     */
    public function __serialize()
    {
        return [
            'sender' => $this->sender,
            'subject' => $this->subject,
            'message' => $this->html,
            'recipients' => $this->recipients
        ];
    }

    /**
     * Constructs the message from array. 
     * 
     * @return void
     */
    public function __unserialize(array $data)
    {
        $this->sender = get_value('sender', $data, '');
        $this->subject = get_value('subject', $data, '');
        $this->html = get_value('message', $data, '');
        $this->recipients = get_value('recipients', $data, []);;
    }

    /**
     * Formats recipients according to RFC 2822.
     * 
     * @param  array $recipient [Email, Name]
     * @return string
     */
    protected function formatToRFC(array $recipient)
    {
        $email = isset($recipient[0]) ? $recipient[0] : '';
        $optional_name = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', isset($recipient[1]) ? $recipient[1] : '');
        return is_string($optional_name) && strlen($optional_name) > 0 ? "$optional_name <$email>" : $email;
    }
}
