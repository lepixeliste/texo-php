<?php

namespace Core\Pdo;

/**
 * The model relationship helper.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class Relation
{
    /** One-To-Many Relationship */
    const TO_ONE = 1;
    /** Many-To-Many Relationship */
    const TO_MANY = 10;

    /**
     * The relationship type.
     *
     * @var int
     */
    protected $type;

    /**
     * The relationship model.
     *
     * @var \Core\Pdo\Model
     */
    protected $foreignModel;

    /**
     * The relationship key.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * The local model.
     *
     * @var \Core\Pdo\Model
     */
    protected $localModel;

    /**
     * The local key.
     *
     * @var string
     */
    protected $localKey;

    /**
     * The pivot keys.
     *
     * @var string[]
     */
    protected $pivotKeys = [];

    /**
     * The local pivot key.
     *
     * @var string|null
     */
    protected $pivotLocal;

    /**
     * The foreign pivot key.
     *
     * @var string|null
     */
    protected $pivotForeign;

    /**
     * The pivot table name.
     *
     * @var string|null
     */
    protected $pivotTable;

    /**
     * The result query sorts.
     *
     * @var array
     */
    protected $sorts = [];

    /**
     * The result query conditions.
     *
     * @var array
     */
    protected $where = [];

    /**
     * The result query limit.
     *
     * @var int
     */
    protected $limit = 0;

    /**
     * The result query if loaded.
     *
     * @var mixed
     */
    protected $values;

    /**
     * Check if relation is loaded from Db.
     *
     * @var mixed
     */
    protected $loaded = false;

    /**
     * @param  int $type The relation type `Relation::TO_ONE` or `Relation::TO_MANY`
     * @param  \Core\Pdo\Model $foreign_model The foreign model
     * @param  \Core\Pdo\Model $local_model The local model
     * @param  string|null $foreign_key The relationship foreign key, or foreign primary key if not set
     * @param  string|null $local_key The relationship local key, or local primary key if not set
     * @return void
     */
    public function __construct($type, Model $foreign_model, Model $local_model, $foreign_key = null, $local_key = null)
    {
        $this->type = in_array($type, [static::TO_ONE, static::TO_MANY]) ? $type : static::TO_ONE;

        $this->foreignModel = $foreign_model;
        $this->foreignKey = !isset($foreign_key) ? $this->foreignModel->primaryKey() : $foreign_key;

        $this->localModel = $local_model;
        $this->localKey = !isset($local_key) ? $this->localModel->primaryKey() : $local_key;
    }

    /**
     * Gets the relation type.
     * 
     * @return int
     */
    public function type()
    {
        return $this->type;
    }

    /**
     * Gets the foreign model class name.
     * 
     * @return string
     */
    public function joinClass()
    {
        return $this->foreignModel::class;
    }

    /**
     * Gets the foreign model.
     * 
     * @return \Core\Pdo\Model
     */
    public function joinModel()
    {
        return $this->foreignModel;
    }

    /**
     * Gets the foreign model table.
     * 
     * @return string
     */
    public function joinTable()
    {
        return $this->foreignModel->table();
    }

    /**
     * Gets the foreign key.
     * 
     * @return string
     */
    public function joinKey()
    {
        return $this->foreignKey;
    }

    /**
     * Gets the local model table.
     * 
     * @return string
     */
    public function localTable()
    {
        return $this->localModel->table();
    }

    /**
     * Gets the local key.
     * 
     * @return string
     */
    public function localKey()
    {
        return $this->localKey;
    }

    /**
     * Gets the pivot table name in Many-To-Many relationship, if any.
     * 
     * @return string|null
     */
    public function pivotTable()
    {
        return $this->pivotTable;
    }

    /**
     * Gets the pivot local key in Many-To-Many relationship, if any.
     * 
     * @return string|null
     */
    public function localPivot()
    {
        return $this->pivotLocal;
    }

    /**
     * Gets the pivot foreign key in Many-To-Many relationship, if any.
     * 
     * @return string|null
     */
    public function joinPivot()
    {
        return $this->pivotForeign;
    }

    /**
     * Gets the pivot keys in Many-To-Many relationship.
     * 
     * @return string[]
     */
    public function pivotKeys()
    {
        return $this->pivotKeys;
    }

    /**
     * Checks if the relationship has any pivot table.
     * 
     * @return bool
     */
    public function hasPivot()
    {
        return is_string($this->pivotTable) && strlen($this->pivotTable) > 0;
    }

    /**
     * Adds a pivot table in Many-To-Many relationship.
     * 
     * @param  string $table The pivot table name
     * @param  string $foreign_key The pivot foreign key
     * @param  string $local_key The pivot local key
     * @param  string[] $extra_keys Extra columns to load from pivot table
     * @return self
     */
    public function withPivot($table, $foreign_key, $local_key, $extra_keys = [])
    {
        $this->pivotTable = $table;
        $this->pivotForeign = $foreign_key;
        $this->pivotLocal = $local_key;
        $this->pivotKeys = $extra_keys;
        return $this;
    }

    /**
     * Sorts the results in ascending or descending order.
     * 
     * @param  string $column The column name
     * @param  string $direction Either `asc` or `desc`, `asc` by default 
     * @return self
     */
    public function orderBy($column, $direction = 'asc')
    {
        $this->sorts[] = [$column, $direction];
        return $this;
    }

    /**
     * Specifies a search condition for the rows returned by a query.
     * 
     * @param  string $column The column to look up
     * @param  string $operator Any valid logical operator
     * @param  string $value The value to look up
     * @return self
     */
    public function where($column, $operator, $value)
    {
        $c = $column;
        if (is_string($c) && !(bool)preg_match('/(\w+)\.(\w+)/', $c)) {
            $c = $this->joinTable() . '.' . $column;
        }
        $this->where[] = [$c, $operator, $value];
        return $this;
    }

    /**
     * Sorts the results in randomly.
     * 
     * @return self
     */
    public function orderRandom()
    {
        $this->sorts[] = ['RAND()'];
        return $this;
    }

    /**
     * Limits the number of rows returned in a query result.
     * 
     * @param  int $n Limit value
     * @return self
     */
    public function limit($n)
    {
        $this->limit = $n;
        return $this;
    }

    /**
     * Prepares the relationship query.
     * 
     * @param  array $ids The relationship Ids to query
     * @return \Core\Pdo\SqlQuery
     */
    protected function makeQuery($ids)
    {
        $c = is_array($ids) ? count($ids) : 0;
        $lp = $this->localModel->primaryKey();
        $lk = $this->localKey;
        $lt = $this->localTable();

        $ft = $this->joinTable();
        $fk = $this->foreignKey;

        $args = $c > 0 ? implode(',', array_fill(0, $c, '?')) : '?';

        $q = SqlQuery::table($ft)->select([$ft, '*']);

        if (isset($this->pivotTable)) {
            $pt = $this->pivotTable;
            $pl = $this->pivotLocal;
            $pk = $this->pivotForeign;

            $q->select([$pt, $pl])
                ->select(...array_map(function ($key) use ($pt) {
                    return [$pt, $key];
                }, $this->pivotKeys))
                ->join($pt, [$pt, $pk], [$ft, $fk])
                ->whereRaw("`$pt`.`$pl` IN ($args)", $c > 0 ? array_values($ids) : [0]);
        } else {
            $q->join($lt, [$lt, $lk], [$ft, $fk])
                ->whereRaw($c > 1 ? "`$lt`.`$lp` IN ($args)" : "`$lt`.`$lp` = ?", $c > 0 ? array_values($ids) : [0]);
        }

        if (count($this->where) > 0) {
            foreach ($this->where as $where) {
                $q->where($where[0], $where[1], $where[2]);
            }
        }

        if (count($this->sorts) > 0) {
            foreach ($this->sorts as $sort) {
                if (!isset($sort[0])) {
                    continue;
                }
                $arg = $sort[0];
                if (isset($sort[1])) {
                    $q->orderBy($sort[0], $sort[1]);
                } elseif ($arg === 'RAND()') {
                    $q->orderRandom();
                }
            }
        }
        if ($this->limit > 0) {
            $q->limit($this->limit);
        }

        return $q;
    }

    /**
     * Performs the relationship request.
     * 
     * @param  \Core\Pdo\Db $db The current database instance
     * @param  array $ids The relationship Ids to query
     * @return mixed
     */
    public function call(Db $db, $ids)
    {
        if ($this->loaded) {
            return $this->values;
        }

        $query = $this->makeQuery($ids);
        $results = $query->run($db, $this->joinClass());

        $this->loaded = is_array($ids) && count($ids) > 0;
        switch ($this->type) {
            case static::TO_ONE:
                return $this->values = $results->first();
            case static::TO_MANY:
                return $this->values = $results;
            default:
                return $this->values = null;
        }
    }

    /**
     * Refresh the relationship request next time it is called.
     */
    public function refresh()
    {
        $this->loaded = false;
    }
}
