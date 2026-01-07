<?php

namespace Core;

use Core\Psr\Container\ContainerInterface;
use Core\Psr\Container\ContainerException;
use ReflectionClass;
use ReflectionException;

/**
 * A PSR-11 compliant container for managing class dependencies and performing dependency injection.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class Container implements ContainerInterface
{
    /**
     * Container items.
     *
     * @var array
     */
    protected $items = [];

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for
     * @return mixed Entry
     * @throws ContainerExceptionInterface  No entry was found for **this** identifier
     */
    public function get(string $id)
    {
        try {
            $item = $this->resolve($id);
            if (!($item instanceof ReflectionClass)) {
                return $item;
            }
            return $this->instantiate($item);
        } catch (ReflectionException $e) {
            throw new ContainerException(sprintf('No entry was found for %s identifier', $id), 0, $e);
        }
    }

    /**
     * Gets true if the container can return an entry for the given identifier.
     * Gets false otherwise.
     *
     * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
     * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $id Identifier of the entry to look for
     * @return bool
     */
    public function has(string $id): bool
    {
        try {
            $item = $this->resolve($id);
        } catch (ReflectionException $e) {
            return false;
        }
        if ($item instanceof ReflectionClass) {
            return $item->isInstantiable();
        }
        return isset($item);
    }

    public function set($id, $item)
    {
        $this->items[$id] = $item;
        return $this;
    }

    /**
     * Resolves the information about any class.
     *
     * @param string $id A string containing the name of the class to reflect
     * @return \ReflectionClass
     * @throws \ReflectionException
     */
    protected function resolve($id)
    {
        try {
            if (isset($this->items[$id])) {
                $item = $this->items[$id];
                if (is_callable($item)) {
                    return $item();
                }
            }
            return (new ReflectionClass($id));
        } catch (ReflectionException $e) {
            throw $e;
        }
    }

    /**
     * Creates a new class instance from the given information.
     * Gets null if object could not be instantiated.
     *
     * @param \ReflectionClass $item The Reflection class to lookup
     * @return object|null
     */
    protected function instantiate(ReflectionClass $item)
    {
        $constructor = $item->getConstructor();
        if (is_null($constructor) || $constructor->getNumberOfRequiredParameters() == 0) {
            return $item->newInstance();
        }
        $params = [];
        foreach ($constructor->getParameters() as $param) {
            if ($type = $param->getType()) {
                $params[] = $this->get($type->getName());
            }
        }
        return $item->newInstanceArgs($params);
    }
}
