<?php

namespace Core;

use ArrayAccess;
use Countable;
use Iterator;
use JsonSerializable;
use Serializable;

/**
 * Convenient wrapper for working with arrays of data.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class Collection implements ArrayAccess, Iterator, Countable, JsonSerializable, Serializable
{
    /**
     * The items contained in the collection.
     *
     * @var array
     */
    protected $items = [];

    /**
     * The index position.
     *
     * @var int
     */
    private $position = 0;

    /**
     * @param array $items An optional array of items
     * @return void
     */
    public function __construct($items = [])
    {
        $this->items = $items;
        $this->position = 0;
    }

    /**
     * Gets the total number of items in the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Gets the current element.
     *
     * @return mixed
     */
    public function current(): mixed
    {
        return $this->items[$this->position];
    }

    /**
     * Gets the key of the current element.
     *
     * @return mixed
     */
    public function key(): mixed
    {
        return $this->position;
    }

    /**
     * Moves forward to next element.
     *
     * @return void
     */
    public function next(): void
    {
        ++$this->position;
    }

    /**
     * Rewinds to the first element.
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Checks if current position is valid.
     *
     * @return bool
     */
    public function valid(): bool
    {
        return isset($this->items[$this->position]);
    }

    /**
     * Whether an offset exists.
     * 
     * @param  int $offset
     *
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * Offset to retrieve.
     * 
     * @param  int $offset
     * @return mixed
     */
    public function offsetGet($offset): mixed
    {
        return isset($this->items[$offset]) ? $this->items[$offset] : null;
    }

    /**
     * Assigns a value to the specified offset.
     * 
     * @param int $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * Unsets an offset.
     * 
     * @param int $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * Specifies data which should be serialized to JSON. 
     * 
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return $this->all();
    }

    /**
     * String representation of the collection. 
     * 
     * @return string
     */
    public function serialize(): string
    {
        $encode = json_encode($this->jsonSerialize());
        return $encode !== false ? $encode : '';
    }

    /**
     * Constructs the collection from array. 
     * 
     * @return void
     */
    public function unserialize(string $data): void
    {
        $json = json_decode($data, true);
        if (is_array($json)) {
            $this->__unserialize($json);
        }
    }

    /**
     * Data representation of the collection. 
     * 
     * @return mixed
     */
    public function __serialize()
    {
        return $this->jsonSerialize();
    }

    /**
     * Constructs the collection from array. 
     * 
     * @return void
     */
    public function __unserialize(array $data)
    {
        $this->items = $data;
    }

    /**
     * Prepends elements to the beginning of the collection.
     * 
     * @param array $values Any given items
     * @return self
     */
    public function unshift(...$values)
    {
        array_unshift($this->items, ...$values);
        return $this;
    }

    /**
     * Pushes elements onto the end of the collection.
     * 
     * @param array $values Any given items
     * @return self
     */
    public function push(...$values)
    {
        foreach ($values as $v) {
            $this->items[] = $v;
        }
        return $this;
    }

    /**
     * Pops the element off the end of the collection and return it.
     *
     * @return mixed|null
     */
    public function pop()
    {
        return array_pop($this->items);
    }

    /**
     * Splits the current items into chunks and returns a new Collection instance.
     * 
     * @param  int $length The size of each chunk
     * @return \Core\Collection
     */
    public function chunk($length)
    {
        return new static(array_chunk($this->items, $length, true));
    }

    /**
     * Determines whether the collection is empty.
     * 
     * @return bool
     */
    public function empty()
    {
        return empty($this->items);
    }

    /**
     * Gets the first element of the collection or null if the collection is empty.
     * 
     * @return mixed|null
     */
    public function first()
    {
        return count($this->items) > 0 ? $this->items[0] : null;
    }

    /**
     * Gets the last element of the collection or null if the collection is empty.
     * 
     * @return mixed|null
     */
    public function last()
    {
        $c = count($this->items);
        return $c > 0 ? $this->items[$c - 1] : null;
    }

    /**
     * Gets the element at given index or null if out of range.
     * 
     * @param  int $index Index to retrieve
     * @return mixed|null
     */
    public function at($index)
    {
        $c = count($this->items);
        return $index < $c && $index > -1 ? $this->items[$index] : null;
    }

    /**
     * Gets the underlying array represented by the collection.
     * 
     * @return array
     */
    public function all()
    {
        return $this->items;
    }

    /**
     * Iterates through the collection, pass each value to the given callback 
     * and returns a new Collection instance.
     * 
     * @param  callable $callable A callable to run for each element
     * @return \Core\Collection
     */
    public function map(callable $callable)
    {
        $items = [];
        $c = count($this->items);
        for ($i = 0; $i < $c; $i++) {
            $items[] = $callable($this->items[$i], $i, $this->items);
        }
        return new static($items);
        // return new static(array_map($callable, $this->items));
    }

    /**
     * Iteratively reduce the array to a single value using a callback function.
     * 
     * @param  callable $callable A callable to run for each element
     * @param  mixed If the optional initial is available, it will be used at the beginning of the process, or as a final result in case the array is empty.
     * @return mixed
     */
    public function reduce(callable $callable, $initial = null)
    {
        $i = 0;
        return array_reduce($this->items, function ($memo, $item) use ($callable, &$i) {
            $r = $callable($memo, $item, $i);
            $i++;
            return $r;
        }, $initial);
    }

    /**
     * Removes duplicate values and returns a new Collection instance. 
     * 
     * @return \Core\Collection
     */
    public function unique()
    {
        $uniques = array_values(array_unique($this->items));
        return new static($uniques);
    }

    /**
     * Sorts the collection using a comparison function. 
     * 
     * @param  callable|null $callable The optional comparison function
     * @return self
     */
    public function sort(callable $callable = null)
    {
        if (is_callable($callable)) {
            usort($this->items, $callable);
        } else {
            sort($this->items);
        }
        return $this;
    }

    /**
     * Iterates over each value passing them to the callback function
     * and returns a new Collection instance. 
     * 
     * @param  callable $callable The callback function to use
     * @return \Core\Collection
     */
    public function filter(callable $callable)
    {
        $filtered = array_values(array_filter($this->items, $callable));
        return new static($filtered);
    }

    /**
     * Extracts a slice of the items and returns a new Collection instance. 
     * 
     * @param  int $from Offset to start at
     * @param  int $to Offset to end up at
     * @return \Core\Collection
     */
    public function slice($from, $to)
    {
        $length = abs($to - $from);
        return new static(array_slice($this->items, $from, $length));
    }

    /**
     * Removes a portion of the items, replaces it with something else
     * and returns a new Collection instance. 
     * 
     * @param  int $from Offset to start at
     * @param  int $length That many elements to be removed
     * @param  int $values The removed elements to be replaced with
     * @return \Core\Collection
     */
    public function splice($offset, $length, ...$values)
    {
        return new static(array_splice($this->items, $offset, $length, $values));
    }

    /**
     * Gets the index of the first element in the collection that satisfies the provided testing function. 
     * 
     * @param  callable $callable The callback function to use
     * @return int
     */
    public function findIndex($callable)
    {
        if (!is_callable($callable)) {
            return -1;
        }
        $c = count($this->items);
        for ($i = 0; $i < $c; $i++) {
            if ($callable($this->items[$i], $i)) {
                return $i;
            }
        }
        return -1;
    }

    /**
     * Gets the first element in the collection that satisfies the provided testing function or null. 
     * 
     * @param  callable $callable The callback function to use
     * @return mixed|null
     */
    public function find($callable)
    {
        $idx = $this->findIndex($callable);
        return $idx > -1 ? $this->items[$idx] : null;
    }
}
