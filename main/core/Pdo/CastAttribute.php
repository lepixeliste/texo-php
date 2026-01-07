<?php

namespace Core\Pdo;

/**
 * The `get` method is responsible for transforming a raw value from the database into a cast value, 
 * while the `set` method should transform a cast value into a raw value that can be stored in the database.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

interface CastAttribute
{
    /**
     * Casts the given value.
     *
     * @param  \Core\Pdo\Model $model
     * @param  string $key
     * @param  mixed  $value
     * @param  mixed  $attributes
     * @return mixed|null
     */
    public function get(Model $model, $key, $value, $attributes);

    /**
     * Prepares the given value.
     *
     * @param  \Core\Pdo\Model $model
     * @param  string  $key
     * @param  mixed   $value
     * @param  mixed   $attributes
     * @return void
     */
    public function set(Model $model, $key, $value, $attributes);
}
