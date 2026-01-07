<?php

namespace Core;

use JsonSerializable;
use Serializable;

/**
 * Core standard object with dynamic properties.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class StdObject implements JsonSerializable, Serializable
{
    /**
     * Reads data from inaccessible (protected or private) or non-existing properties.
     * 
     * @param  string $name The name of the property
     * @return mixed|null
     */
    public function __get($name)
    {
        return property_exists($this, $name) ? $this->$name : null;
    }

    /**
     * Gets the properties of the given object
     *
     * @return array
     */
    public function toArray()
    {
        return get_object_vars($this);
    }

    /**
     * Specifies data which should be serialized to JSON. 
     * 
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * String representation of the object. 
     * 
     * @return string
     */
    public function serialize(): string
    {
        $encode = json_encode($this->jsonSerialize());
        return $encode !== false ? $encode : '';
    }

    /**
     * Constructs the object from array. 
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
     * Data representation of the object. 
     * 
     * @return mixed
     */
    public function __serialize()
    {
        return get_object_vars($this);
    }

    /**
     * Constructs the object from array. 
     * 
     * @return void
     */
    public function __unserialize(array $data)
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
    }
}
