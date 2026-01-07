<?php

namespace Core\Mail;

/**
 * A RFC 2822 compliant mail recipient helper class.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class MailRecipient
{
    /** @var string */
    public $email = '';

    /** @var string */
    public $name = '';

    /**
     * @param  string $email
     * @param  string $optional_name
     * @return void
     */
    public function __construct($email = '', $optional_name = '')
    {
        $this->email = $email;
        $this->name = $optional_name;
    }

    /**
     * Gets the string representation of the recipient.
     *
     * @return string
     */
    public function __toString()
    {
        $email = filter_var($this->email, FILTER_VALIDATE_EMAIL, FILTER_NULL_ON_FAILURE);
        $name = is_string($this->name) && strlen($this->name) > 0 ? $this->name : '';
        return strlen($this->name) > 0 ? "$name <$email>" : $email;
    }

    /**
     * Formats into a valid recipient string.
     * 
     * @return string
     */
    public function format()
    {
        return strval($this);
    }

    /**
     * Checks if the recipient string is valid.
     * 
     * @return bool
     */
    public function isValid()
    {
        return (bool)(filter_var($this->email, FILTER_VALIDATE_EMAIL, FILTER_NULL_ON_FAILURE) !== null);
    }
}
