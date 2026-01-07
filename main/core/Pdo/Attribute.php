<?php

namespace Core\Pdo;

/**
 * Defining custom attribute with a `getter` and optional `setter` functions
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class Attribute
{
    /**
     * The getter function.
     *
     * @var callable|null
     */
    protected $getter;

    /**
     * The setter function.
     *
     * @var callable|null
     */
    protected $setter;

    /**
     * @param  callable $getter 
     * @param  callable|null $setter
     * @return void
     */
    public function __construct(callable $getter, callable $setter = null)
    {
        $this->getter = $getter;
        $this->setter = $setter;
    }

    /**
     * Resolves the value by calling the getter function.
     * 
     * @param  callable $key 
     * @return mixed|null
     */
    public function get($key)
    {
        return isset($this->getter) && is_callable($this->getter) ? call_user_func($this->getter, $key) : null;
    }

    /**
     * Set the value by key, if setter is available.
     * 
     * @param  string $key 
     * @param  mixed $value 
     * @return void
     */
    public function set($key, $value)
    {
        if (!isset($this->setter) || !is_callable($this->setter)) {
            return;
        }
        call_user_func($this->setter, $key, $value);
    }
}
