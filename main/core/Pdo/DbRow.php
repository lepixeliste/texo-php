<?php

namespace Core\Pdo;

use Core\StdObject;

/**
 * The default object returned by the `PDO::FETCH_CLASS` method
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class DbRow extends StdObject
{
    /**
     * The Db instance.
     *
     * @var \Core\Pdo\Db
     */
    protected $db;

    /** @var array */
    protected $columns = [];

    /** @var array */
    protected $values = [];

    /** @var array */
    protected $attributes = [];

    /**
     * @param  \Core\Pdo\Db $db
     * @return void
     */
    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    /**
     * Gets the associated database instance.
     * 
     * @return \Core\Pdo\Db $db
     */
    public function db()
    {
        return $this->db;
    }

    /**
     * Gets the table columns.
     * 
     * @return array
     */
    public function columns()
    {
        return $this->columns;
    }

    /**
     * Gets the table values.
     * 
     * @return array
     */
    public function values()
    {
        return $this->values;
    }

    /**
     * Looks for the index of the columns by name, or -1 if not found.
     * 
     * @param  string $column The column name
     * @return int
     */
    public function index($column)
    {
        foreach ($this->columns as $i => $c) {
            if ($column === $c) {
                return $i;
            }
        }
        return -1;
    }

    /**
     * Returns for the table values by separator key.
     * 
     * @param  string|null $key The separator key name
     * @return array
     */
    public function getJoinValues($key = null)
    {
        $start = isset($key) ? -1 : 0;
        $end = count($this->columns);
        foreach ($this->columns as $i => $column) {
            if ($start > -1) {
                if ((bool)preg_match('/{%\w+%}/', $column)) {
                    $end = $i;
                    break;
                }
            }
            if (!isset($key)) {
                continue;
            }
            if (strpos($column, '{%' . $key . '%}') !== false) {
                $start = $i + 1;
            }
        }

        if ($start < 0) {
            return [];
        }

        $from = $i > 0 ? $start : 0;
        $length = abs($end - $from);
        $columns = array_slice($this->columns, $from, $length);
        $slice = array_slice($this->values, $from, $length);

        return array_combine($columns, $slice);
    }

    /**
     * Reads data from inaccessible (protected or private) or non-existing properties.
     * 
     * @param  string $name The name of the property
     * @return mixed|null
     */
    public function __get($name)
    {
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }
        return property_exists($this, $name) ? $this->$name : null;
    }

    /**
     * Runs when writing data to inaccessible (protected or private) or non-existing properties.
     * 
     * @return void
     */
    public function __set($name, $value)
    {
        $this->values[] = $value;
        $this->columns[] = $name;

        if (property_exists($this, $name) || array_key_exists($name, $this->attributes)) {
            $name2 = implode('', [$name, count(array_filter($this->columns, function ($column) use ($name) {
                return $column === $name;
            })) + 1]);
            $this->attributes[$name2] = $value;
            return;
        }

        $this->attributes[$name] = $value;
    }

    /**
     * Gets the array representation of the table row.
     * 
     * @return array
     */
    public function toArray()
    {
        return array_combine($this->columns, $this->values);
    }
}
