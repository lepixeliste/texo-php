<?php

namespace Core\Pdo;

use JsonSerializable;
use Core\Collection;

/**
 * Transformation layer that sits between the models and the JSON responses.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class ModelResource implements JsonSerializable
{
    /**
     * The Model instance.
     *
     * @var \Core\Pdo\Model|object|array
     */
    protected $model;

    /**
     * @param  \Core\Pdo\Model $model
     * @return void
     */
    public function __construct($model)
    {
        if (!is_object($model)) {
            throw new ModelException(ModelException::INVALID_OBJECT);
        }
        $this->model = $model;
    }

    /**
     * Maps a collection of models.
     * 
     * @param  \Core\Collection $collection Collection of `\Core\Pdo\Model`
     * @param  array $arguments Optional array of attributes
     * @return \Core\Collection
     */
    public static function collection(Collection $collection, $arguments = [])
    {
        if (!isset($collection) || !($collection instanceof Collection)) {
            return collect();
        }

        return $collection->map(function ($item) use ($arguments) {
            foreach ($arguments as $key => $val) {
                $item->{$key} = $val;
            }
            return is_object($item) ? new static($item) : [];
        });
    }

    /**
     * Gets the associated model.
     * 
     * @return \Core\Pdo\Model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Triggered when invoking inaccessible methods in an object context.
     * 
     * @param  string $name
     * @param  array  $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->model, $name], $arguments);
    }

    /**
     * Reads data from the underlying model.
     * 
     * @param  string $name The name of the property
     * @return mixed|null
     */
    public function __get($name)
    {
        return $this->model->__get($name);
    }

    /**
     * Writes data to the underlying model.
     * 
     * @param  string $name The name of the property
     * @param  mixed $value
     * @return void
     */
    public function __set($name, $value)
    {
        $this->model->__set($name, $value);
    }

    /**
     * Transforms the resource into an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [];
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
}
