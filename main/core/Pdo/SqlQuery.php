<?php

namespace Core\Pdo;

use Exception;

/**
 * Convenient wrapper for building SQL statements.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class SqlQuery implements DbQuery
{
    /**
     * The list of SQL logical operators
     *
     * @var string[]
     */
    public const OPERATORS = [
        '&',
        '>',
        '>>',
        '>=',
        '<',
        '<>',
        '!=',
        '<<',
        '<=',
        '<=>',
        '%',
        '*',
        '+',
        '-',
        '->',
        '->>',
        '/',
        ':=',
        '=',
        '^',
        '~',
        'AND',
        '&&',
        'BETWEEN',
        'BINARY',
        'CASE',
        'OR',
        '||',
        'XOR',
        '|',
        'NOT',
        '!',
        'IN',
        'NOT IN',
        'IS',
        'IS NOT',
        'LIKE',
        'NOT LIKE',
        'NOT REGEXP',
        'REGEXP'
    ];

    /** The SQL keyword for the current date and time */
    public const CURRENT_TIMESTAMP = 'CURRENT_TIMESTAMP';

    /**
     * The query command.
     *
     * @var string
     */
    protected $command = '';

    /**
     * The query table.
     *
     * @var string
     */
    protected $table = '';

    /**
     * The query statements.
     *
     * @var string[]
     */
    protected $statements = [];

    /**
     * The query parameters.
     *
     * @var mixed[]
     */
    protected $params = [];

    /**
     * Check if the query is distinct.
     *
     * @var bool
     */
    protected $distinct = false;

    /**
     * The query joins.
     *
     * @var array
     */
    protected $joins = [];

    /**
     * The query conditions.
     *
     * @var array
     */
    protected $where = [];

    /**
     * The query filter conditions.
     *
     * @var array
     */
    protected $filters = [];

    /**
     * The query sorts.
     *
     * @var array
     */
    protected $sorts = [];

    /**
     * The query groups.
     *
     * @var array
     */
    protected $grouped = [];

    /**
     * The query offset.
     *
     * @var int
     */
    protected $offset = 0;

    /**
     * The query limit.
     *
     * @var int
     */
    protected $limit = 0;

    /**
     * The query last INSERT returned Id.
     *
     * @var string|null
     */
    protected $returnedId = null;

    /**
     * Returns an instance for the associated `$table`.
     * 
     * @param string $table The table name
     * @return static
     */
    public static function table($table)
    {
        $query = new static();
        $query->table = $table;
        return $query;
    }

    /**
     * Returns the prepared statement from query.
     * 
     * @param string $query Any valid SQL statement
     * @param array $params The query binding parameters 
     * @return string
     */
    public static function combine($query, $params)
    {
        if (!is_array($params) || empty($params)) {
            return $query;
        }

        $debug = $query;
        $replace = $params;
        $count = 0;
        $debug = preg_replace_callback('/\?/m', function ($match) use (&$replace, &$count) {
            $val = $count < count($replace) ? $replace[$count] : 'undefined';
            $count++;
            return isset($val) ? (is_numeric($val) ? $val : "\"$val\"") : 'NULL';
        }, $debug);
        return is_array($debug) && count($debug) > 0 ? $debug[0] : (is_string($debug) ? $debug : '');
    }

    /**
     * Runs a bulk data transation on a Db instance.
     * 
     * @param  \Core\Pdo\Db $db The database instance
     * @param  array<\Core\Pdo\SqlQuery> $queries An array of SqlQuery instances
     * @param  int $size The size of bulk 
     * @return boolean
     */
    public static function bulk(Db $db, $queries, $size = 200)
    {
        $success = true;
        $stmt_queries = [];
        $stmt_params = [];

        foreach ($queries as $query) {
            if (!($query instanceof SqlQuery)) {
                continue;
            }
            $stmt_queries[] = $query->get();
            $stmt_params[] = $query->params();
        }

        $chunks_queries = array_chunk($stmt_queries, $size);
        $chunks_params = array_chunk($stmt_params, $size);
        foreach ($chunks_queries as $c => $chunk) {
            $chunk_query = ['START TRANSACTION', ...$chunk, 'COMMIT'];
            $chunk_params = array_reduce($chunks_params[$c], function ($acc, $cur) {
                array_push($acc, ...$cur);
                return $acc;
            }, []);
            try {
                $db->execute(implode(';', $chunk_query), $chunk_params, null);
            } catch (Exception $e) {
                $success = false;
                continue;
            }
        }
        return $success;
    }

    /**
     * Prepares a `select` statement.
     * 
     * @param array $arg Any `select` expression 
     * @return self
     */
    public function select(...$arg)
    {
        $this->command = 'SELECT';
        array_push($this->statements, ...$this->setStatement($arg));
        return $this;
    }

    /**
     * Prepares a `insert` statement.
     * 
     * @param array $values Any key-value pair 
     * @return self
     */
    public function insert($values)
    {
        $this->command = 'INSERT';
        $columns = array_keys($values);
        array_push($this->params, ...array_values($values));
        array_push($this->statements, ...$this->setStatement($columns));
        return $this;
    }

    /**
     * Prepares a `update` statement.
     * 
     * @param array $values Any key-value pair 
     * @return self
     */
    public function update($values)
    {
        $this->command = 'UPDATE';
        $columns = array_keys($values);
        array_push($this->params, ...array_values($values));
        array_push($this->statements, ...$this->setStatement($columns));
        return $this;
    }

    /**
     * Prepares a `delete` statement.
     * 
     * @return self
     */
    public function delete()
    {
        $this->command = 'DELETE';
        return $this;
    }

    /**
     * Prepares a `show` statement.
     * 
     * @return self
     */
    public function show()
    {
        $this->command = 'SHOW';
        return $this;
    }

    /**
     * Prepares a `create table` statement.
     * 
     * @param  array $columns Descriptions of the column
     * @param  array $indexes Columns indexes to add
     * @return self
     */
    public function createTable($columns, $indexes = [])
    {
        $this->command = 'CREATE';
        $this->statements = [];
        foreach ($columns as $name => $column) {
            $not_null = boolval(get_value('null', $column, 0));
            $primary = boolval(get_value('primary', $column));
            $auto = boolval(get_value('auto', $column));
            $type = strtoupper(get_value('type', $column, 'INT'));
            if ($auto) {
                $primary = true;
            }

            $stmt = [$name, $type];

            if ($not_null || $primary) {
                $stmt[] = 'NOT NULL';
            }
            $default = get_value('default', $column);
            if (isset($default)) {
                if (is_bool($default)) {
                    $default = $default == false ? 'FALSE' : 'TRUE';
                } else {
                    $default = $default !== static::CURRENT_TIMESTAMP ? "'$default'" : $default;
                }
                $stmt[] = "DEFAULT $default";
            }

            if ($auto || ($type === 'INT' && $primary)) {
                $stmt[] = 'AUTO_INCREMENT';
            }
            if ($primary) {
                $stmt[] = 'PRIMARY KEY';
            }
            $this->statements[] = implode(' ', $stmt);
        }

        $this->params = [];
        foreach ($indexes as $index) {
            $columns = is_string($index) ? [$index] : (is_array($index) ? $index : []);
            // $index_name = isset($columns[0]) ? $columns[0] : unique_id(6);
            $this->params[] = "INDEX (" . implode(', ', $columns) . ')';
        }
        return $this;
    }

    /**
     * Prepares a `drop table` statement.
     * 
     * @return self
     */
    public function drop()
    {
        $this->command = 'DROP';
        return $this;
    }

    /**
     * Retrieves distinct results from query.
     * 
     * @return self
     */
    public function distinct()
    {
        $this->distinct = true;
        return $this;
    }

    /**
     * Joins a table to query.
     * 
     * @param  string $table The table name to join
     * @param  string $foreign_key The foreign key of the relationship
     * @param  string $local_key The local key of the relationship
     * @return self
     */
    public function join($table, $foreign_key, $local_key)
    {
        return $this->innerJoin($table, $foreign_key, $local_key);
    }

    /**
     * Inner-joins a table to query.
     * 
     * @param  string $table The table name to join
     * @param  string $foreign_key The foreign key of the relationship
     * @param  string $local_key The local key of the relationship
     * @return self
     */
    public function innerJoin($table, $foreign_key, $local_key)
    {
        if (!$this->hasJoin($table)) {
            $this->joins[] = ['INNER JOIN', $this->setJoin($table, $foreign_key, $local_key)];
        }
        return $this;
    }

    /**
     * Left-joins a table to query.
     * 
     * @param  string $table The table name to join
     * @param  string $foreign_key The foreign key of the relationship
     * @param  string $local_key The local key of the relationship
     * @return self
     */
    public function leftJoin($table, $foreign_key, $local_key)
    {
        if (!$this->hasJoin($table)) {
            $this->joins[] = ['LEFT JOIN', $this->setJoin($table, $foreign_key, $local_key)];
        }
        return $this;
    }

    /**
     * Right-joins a table to query.
     * 
     * @param  string $table The table name to join
     * @param  string $foreign_key The foreign key of the relationship
     * @param  string $local_key The local key of the relationship
     * @return self
     */
    public function rightJoin($table, $foreign_key, $local_key)
    {
        if (!$this->hasJoin($table)) {
            $this->joins[] = ['RIGHT JOIN', $this->setJoin($table, $foreign_key, $local_key)];
        }
        return $this;
    }

    /**
     * Cross-joins a table to query.
     * 
     * @param  string $table The table name to join
     * @return self
     */
    public function crossJoin($table)
    {
        if (!$this->hasJoin($table)) {
            $this->joins[] = ['CROSS JOIN', [$table, "`$table`"]];
        }
        return $this;
    }

    /**
     * Specifies filter conditions for a group of rows or aggregates.
     * 
     * @param  string $column The column to filter
     * @param  string $operator Any valid logical operator
     * @param  string $value The value to filter
     * @return self
     */
    public function having($column, $operator, $value)
    {
        if ($this->command !== 'SELECT') {
            return $this;
        }

        $condition = $this->setCondition($column, $operator, $value);
        $this->filters[] = $condition;
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
        if ($this->command === 'INSERT') {
            return $this;
        }

        $condition = $this->setCondition($column, $operator, $value);
        if (empty($this->where)) {
            $this->where[] = [$condition];
        } else {
            $g = count($this->where);
            $this->where[$g - 1][] = $condition;
        }
        return $this;
    }

    /**
     * Specifies a search condition when values are null.
     * 
     * @param  string $column The column to look up
     * @return self
     */
    public function whereNull($column)
    {
        if ($this->command === 'INSERT') {
            return $this;
        }

        $c = $this->assert($column);
        if (empty($c)) {
            return $this;
        }
        $condition = "$c IS NULL";
        if (empty($this->where)) {
            $this->where[] = [$condition];
        } else {
            $g = count($this->where);
            $this->where[$g - 1][] = $condition;
        }
        return $this;
    }

    /**
     * Specifies a search condition when values are not null.
     * 
     * @param  string $column The column to look up
     * @return self
     */
    public function whereNotNull($column)
    {
        if ($this->command === 'INSERT') {
            return $this;
        }

        $c = $this->assert($column);
        if (empty($c)) {
            return $this;
        }
        $condition = "$c IS NOT NULL";
        if (empty($this->where)) {
            $this->where[] = [$condition];
        } else {
            $g = count($this->where);
            $this->where[$g - 1][] = $condition;
        }
        return $this;
    }

    /**
     * Specifies a search condition when rows matches the list of values provided.
     * 
     * @param  string $column The column to look up
     * @param  array $values The list of values to look up
     * @return self
     */
    public function whereIn($column, $values)
    {
        if ($this->command === 'INSERT') {
            return $this;
        }

        $c = $this->assert($column);
        if (empty($c)) {
            return $this;
        }

        $fill = implode(',', array_fill(0, count($values), '?'));
        array_push($this->params, ...$values);
        $condition = "$c IN ($fill)";

        if (empty($this->where)) {
            $this->where[] = [$condition];
        } else {
            $g = count($this->where);
            $this->where[$g - 1][] = $condition;
        }
        return $this;
    }

    /**
     * Specifies a raw search condition for the rows returned by a query.
     * 
     * @param  string $raw The raw search expression
     * @param  string $value The binding parameters
     * @return self
     */
    public function whereRaw($raw, $values = [])
    {
        if ($this->command === 'INSERT') {
            return $this;
        }

        if (count($values) > 0) {
            array_push($this->params, ...$values);
        }

        if (empty($this->where)) {
            $this->where[] = [$raw];
        } else {
            $g = count($this->where);
            $this->where[$g - 1][] = $raw;
        }
        return $this;
    }

    /**
     * Adds another search criteria with `OR` logical condition.
     * 
     * @param  string $column The column to look up
     * @param  string $operator Any valid logical operator
     * @param  string $value The value to look up
     * @return self
     */
    public function orWhere($column, $operator, $value)
    {
        if ($this->command === 'INSERT' || empty($this->where)) {
            return $this;
        }

        $condition = $this->setCondition($column, $operator, $value);
        $this->where[] = [$condition];
        return $this;
    }

    /**
     * Groups rows that have the same values into summary rows.
     * 
     * @param  array $args The column names
     * @return self
     */
    public function groupBy(...$args)
    {
        if ($this->command !== 'SELECT') {
            return $this;
        }

        foreach ($args as $arg) {
            $this->grouped[] = $this->assert($arg);
        }
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
        if ($this->command !== 'SELECT') {
            return $this;
        }

        $d = is_string($direction) ? strtoupper($direction) : 'ASC';
        $sort = $this->assert($column);
        $this->sorts[] = implode(' ', [$sort, $d === 'ASC' || $d === 'DESC' ? $d : 'ASC']);
        return $this;
    }

    /**
     * Sorts the results in randomly.
     * 
     * @return self
     */
    public function orderRandom()
    {
        if ($this->command !== 'SELECT') {
            return $this;
        }

        $this->sorts[] = 'RAND()';
        return $this;
    }

    /**
     * Specifies which row to start from retrieving data.
     * 
     * @param  int $n Offset value
     * @return self
     */
    public function offset($n)
    {
        $this->offset = $n;
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
     * Gets the prepared query statement.
     * 
     * @return string
     * @throws \Core\Pdo\DbQueryException
     */
    public function get()
    {
        $sql = collect();
        $command = strtoupper($this->command);
        $table = $this->table;
        if (empty($table)) {
            throw new DbQueryException(DbQueryException::NO_TABLE);
        }

        $statements = $this->statements;
        $where_conditions = implode(' OR ', array_map(function ($group) {
            $condition = implode(' AND ', $group);
            return count($this->where) > 1 ? "($condition)" : $condition;
        }, $this->where));

        switch ($command) {
            case 'COUNT':
            case 'SELECT': {
                    $sql
                        ->push('SELECT')
                        ->push($this->distinct ? 'DISTINCT' : '')
                        ->push(count($this->statements) > 0 ? trim(implode(', ', $statements)) : "`$table`.*")
                        ->push("FROM `$table`");
                    foreach ($this->joins as $join) {
                        $sql->push($join[0], $join[1][1]);
                    }
                    $sql->push(empty($where_conditions) ? '' : "WHERE $where_conditions");
                    if (count($this->grouped) > 0) {
                        $sql->push('GROUP BY');
                        $sql->push(implode(', ', $this->grouped));
                    }
                    if (count($this->filters) > 0) {
                        $sql->push('HAVING');
                        $sql->push(implode(' AND ', $this->filters));
                    }
                    if (count($this->sorts) > 0) {
                        $sql->push('ORDER BY');
                        $sql->push(implode(', ', $this->sorts));
                    }
                    if ($this->limit > 0) {
                        $sql->push('LIMIT', $this->limit);
                        if ($this->offset > 0) {
                            $sql->push('OFFSET', $this->offset);
                        }
                    }
                    break;
                }
            case 'INSERT': {
                    if (empty($statements)) {
                        throw new DbQueryException(DbQueryException::INVALID_ARGUMENTS);
                    }
                    $fill = implode(', ', array_fill(0, count($this->params), '?'));
                    $sql
                        ->push("$command INTO `$table`")
                        ->push('(' . trim(implode(', ', $statements)) . ')')
                        ->push("VALUES ($fill)");
                    break;
                }
            case 'UPDATE': {
                    if (empty($statements)) {
                        throw new DbQueryException(DbQueryException::INVALID_ARGUMENTS);
                    }
                    $values = implode(', ', array_map(function ($item) {
                        return "$item = ?";
                    }, $statements));
                    $sql
                        ->push("$command `$table`")
                        ->push("SET $values")
                        ->push(empty($where_conditions) ? 'WHERE 0' : "WHERE $where_conditions");
                    break;
                }
            case 'DELETE': {
                    $sql
                        ->push("$command FROM `$table`")
                        ->push(empty($where_conditions) ? 'WHERE 0' : "WHERE $where_conditions");
                    if (count($this->sorts) > 0) {
                        $sql->push('ORDER BY');
                        $sql->push(implode(', ', $this->sorts));
                    }
                    if ($this->limit > 0) {
                        $sql->push('LIMIT', $this->limit);
                    }
                    break;
                }
            case 'SHOW': {
                    $sql
                        ->push("$command COLUMNS FROM `$table`");
                    break;
                }
            case 'CREATE': {
                    $sql
                        ->push("$command TABLE IF NOT EXISTS `$table`")
                        ->push("(\r\n" . implode(",\r\n", $this->statements) . "\r\n);");
                    break;
                }
            case 'DROP': {
                    $sql
                        ->push("$command TABLE `$table`");
                    break;
                }
            default: {
                    throw new DbQueryException(DbQueryException::INVALID_COMMAND);
                }
        }
        $results = array_filter($sql->all(), function ($item) {
            return !empty($item);
        });
        $q = implode(' ', $results);
        return $q;
    }

    /**
     * Gets the binding parameters.
     * 
     * @return mixed[]
     */
    public function params()
    {
        return $this->params;
    }

    /**
     * Gets the last returned Id, if any.
     * 
     * @return int|string|null
     */
    public function returnedId()
    {
        return $this->returnedId;
    }

    /**
     * Runs the query on a Db instance and returns a collection of records.
     * 
     * @param  \Core\Pdo\Db $db The database instance
     * @return \Core\Collection
     * @throws \Core\Pdo\DbQueryException
     */
    public function run(Db $db, $fetch_class = DbRow::class)
    {
        if (empty($this->get())) {
            throw new DbQueryException(DbQueryException::EMPTY_QUERY);
        }
        $results = $db->execute($this->get(), $this->params(), $fetch_class);
        $this->returnedId = $db->lastInsertId();
        return $results;
    }

    /**
     * Returns the number of records.
     * 
     * @param  \Core\Pdo\Db $db The database instance
     * @param  string $column The column name
     * @return int
     */
    public function count(Db $db, $column)
    {
        $this->command = 'COUNT';
        $exp = $this->assert($column);
        $this->statements = ["COUNT($exp) as count_values"];

        if (empty($this->get())) {
            throw new DbQueryException(DbQueryException::EMPTY_QUERY);
        }

        $results = $db->execute($this->get(), $this->params());
        $r = $results->first();
        return null !== $r ? intval($r->count_values) : 0;
    }

    /**
     * Debugs the query.
     * 
     * @return string
     */
    public function debug()
    {
        $sql_string = static::combine($this->get(), $this->params());
        debug($sql_string);
        return $sql_string;
    }

    /**
     * Sets any statement expression.
     * 
     * @param  array $args
     * @return string[]
     */
    protected function setStatement($args)
    {
        $values = [];
        foreach ($args as $arg) {
            $values[] = trim($this->assert($arg));
        }
        return array_filter($values, function ($item) {
            return strlen($item) > 0;
        });
    }

    /**
     * Sets any condition expression.
     * 
     * @param  string $column The column to look up
     * @param  string $operator Any valid logical operator
     * @param  string $value The value to look up
     * @return self
     */
    protected function setCondition($column, $operator, $value)
    {
        $o = strtoupper($operator);
        $c = $this->assert($column);
        $condition = [$c];
        $condition[] = in_array($o, static::OPERATORS) ? $o : '=';
        $condition[] = '?';

        $this->params[] = $value;

        return implode(' ', $condition);
    }

    /**
     * Sets any join expression.
     * 
     * @param  string $table The table name to join
     * @param  string $foreign_key The foreign key of the relationship
     * @param  string $local_key The local key of the relationship
     * @return self
     */
    protected function setJoin($table, $foreign_key, $local_key)
    {
        $re = '/(\w+)\.(\w+)/';
        $fk = is_array($foreign_key) || (bool)preg_match($re, $foreign_key) ? $this->assert($foreign_key) : "`$table`.`$foreign_key`";
        $lk = is_array($local_key) || (bool)preg_match($re, $local_key) ? $this->assert($local_key) : "`{$this->table}`.`$local_key`";
        return [$table, implode(' ', ["`$table`", 'ON', $fk, '=', $lk])];
    }

    /**
     * Checks if query has any join expression for table.
     * 
     * @param  string $table The table name
     * @return self
     */
    protected function hasJoin($table)
    {
        $filtered = array_filter($this->joins, function ($item) use ($table) {
            return $item[1][0] === $table;
        });
        return count($filtered) > 0;
    }

    /**
     * Asserts any expression.
     * 
     * @param  string|string[] $element The table name
     * @return string
     */
    private function assert($expression)
    {
        $re = '/\.`\*`/';
        if (is_array($expression)) {
            $t = isset($expression[0]) ? $expression[0] : $this->table;
            $k = isset($expression[1]) ? $expression[1] : '';
            return preg_replace($re, '.*', "`$t`.`$k`");
        }
        return preg_replace(
            $re,
            '.*',
            preg_replace('/(\w+)\.(\w+)/', '`$1`.`$2`', $expression)
        );
    }
}
