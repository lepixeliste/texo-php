<?php

namespace Core\Pdo;

use Exception;
use JsonSerializable;
use Serializable;
use Core\Collection;
use Core\Logger;
use Core\StdObject;
use Core\Psr\Log\LogLevel;

/**
 * The core object-relational mapper.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class Model implements JsonSerializable, Serializable
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = '';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    protected $incrementing = true;

    /**
     * The name of the "created at" column.
     *
     * @var string|null
     */
    const CREATED_AT = null;

    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    const UPDATED_AT = null;

    /**
     * The name of the "deleted at" column.
     *
     * @var string|null
     */
    const DELETED_AT = null;

    /**
     * The underlying model attributes.
     *
     * @var array<string,mixed>
     */
    protected $attributes = [];

    /**
     * The underlying model changes.
     *
     * @var array<string,mixed>
     */
    protected $changes = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string,string>
     */
    protected $casts = [];

    /**
     * The custom mapping from input.
     *
     * @var array<string,string>
     */
    protected $mapping = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var string[]
     */
    protected $hidden = [];

    /**
     * The attributes that are guarded from input.
     *
     * @var string[]
     */
    protected $guarded = [];

    /**
     * The model prepared statement.
     *
     * @var \Core\Pdo\SqlQuery
     */
    protected $query;

    /**
     * The select statements associated with the query.
     *
     * @var mixed
     */
    protected $select;

    /**
     * The relations to load on query.
     *
     * @var string[]
     */
    protected $with = [];

    /**
     * The relationships associated with the model.
     *
     * @var array
     */
    protected $relations = [];

    /**
     * All of the relationships to be touched.
     *
     * @var array
     */
    protected $touches = [];

    /**
     * To-many relationships to be processed after saving model.
     *
     * @var array
     */
    protected $toMany = [];

    /**
     * The Db instance.
     *
     * @var \Core\Pdo\Db
     */
    protected $db;

    /**
     * @param  \Core\Pdo\Db $db The current Db instance
     * @param  array $attributes The default values for the model attributes
     * @return void
     */
    public function __construct(Db $db, $attributes = [])
    {
        $this->setup($attributes);
        $this->select = [[$this->table, '*']];
        $this->db = $db;

        if (count($this->with) > 0) {
            foreach ($this->with as $with) {
                $this->__set($with, $this->__get($with));
            }
            $this->changes = [];
        }
    }

    /**
     * Returns an instance of the model by Id, if found.
     * 
     * @param  \Core\Pdo\Db $db The current Db instance
     * @param  string|int $id The Id to look up in the database
     * @return static
     */
    public static function find(Db $db, $id)
    {
        $model = new static($db);
        $results = $model
            ->where([$model->table(), $model->primaryKey()], '=', $id)
            ->get();
        return $results->first();
    }

    /**
     * Returns an instance of the model.
     * 
     * @param  \Core\Pdo\Db $db The current Db instance
     * @param  array $relations The relations to load on query
     * @return static
     */
    public static function load(Db $db, $relations = [])
    {
        $model = new static($db);
        return $model->with($relations);
    }

    /**
     * Gets the current database instance.
     * 
     * @return \Core\Pdo\Db
     */
    public function db()
    {
        return $this->db;
    }

    /**
     * Gets the name of the database table.
     * 
     * @return string
     */
    public function table()
    {
        return $this->table;
    }

    /**
     * Gets the primary key of the model, if any.
     * 
     * @return string|null
     */
    public function primaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * Gets the custom mapping from input.
     * 
     * @return array<string,string>
     */
    public function mapping()
    {
        return $this->mapping;
    }

    /**
     * Gets the persistant Id of the model, if any.
     * 
     * @return int|string|null
     */
    public function id()
    {
        $key = $this->primaryKey;
        return $this->__get($key);
    }

    /**
     * Checks if the model is timestamped.
     * 
     * @return bool
     */
    public function isTimestamped()
    {
        return null !== static::CREATED_AT || null !== static::UPDATED_AT;
    }

    /**
     * Checks if the model is persistent into the database.
     * 
     * @return bool
     */
    public function isPersistent()
    {
        return isset($this->attributes[$this->primaryKey]);
    }

    /**
     * Checks if the model attributes have been modified.
     * 
     * @return bool
     */
    public function isDirty()
    {
        return count($this->changes) > 0;
    }

    /**
     * Returns the model attributes which have been modified.
     * 
     * @return array
     */
    public function changes()
    {
        $changes = [];
        $keys = array_keys($this->changes);
        foreach ($keys as $key) {
            $changes[$key] = $this->attributes[$key];
        }
        return $changes;
    }

    /**
     * Returns the model database prepared statement.
     * 
     * @return \Core\Pdo\SqlQuery
     */
    public function query()
    {
        if (!isset($this->query)) {
            $this->query = SqlQuery::table($this->table)->select(...$this->select);
        }
        return $this->query;
    }

    /**
     * Prepares the database query with only the selected columns.
     * 
     * @param  array $args The column names to look up
     * @return self
     */
    public function pluck(...$args)
    {
        $select = (is_array($args) && count($args) > 0 && $args[0] !== '*') ? $args : [[$this->table, '*']];
        $this->query = SqlQuery::table($this->table)->select(...$select);
        return $this;
    }

    /**
     * The Id to look up when queried.
     * 
     * @param  string|int $id The Id to look for
     * @return self
     */
    public function whereId($id)
    {
        $this->query()->where([$this->table(), $this->primaryKey()], '=', $id);
        return $this;
    }

    /**
     * The relation to join in the query.
     * 
     * @param  string $rel The name of the relation
     * @return self
     */
    public function joinRelation($rel)
    {
        $relation = method_exists($this, $rel) ? call_user_func([$this, $rel]) : null;
        if (!($relation instanceof Relation)) {
            throw new ModelException(ModelException::NO_RELATION, $rel);
        }

        if ($relation->hasPivot()) {
            $this->query()->leftJoin($relation->pivotTable(), $relation->localPivot(), [$this->table, $relation->localKey()]);
        } else {
            $this->query()->leftJoin($relation->joinTable(), $relation->joinKey(), $relation->localKey());
        }

        return $this;
    }

    /**
     * Add the relations to load on query.
     * 
     * @param  string[] $relations The relation names
     * @return self
     */
    public function with($relations)
    {
        if (is_array($relations)) {
            foreach ($relations as $rel) {
                if (!in_array($rel, $this->with)) {
                    $this->with[] = $rel;
                }
            }
        }
        return $this;
    }

    /**
     * When the model has one relation.
     * 
     * @param  string      $model       The model class
     * @param  string|null $local_key   The relation local key
     * @param  string|null $foreign_key The relation foreign key
     * @return \Core\Pdo\Relation
     */
    protected function hasOne($model, $local_key = null, $foreign_key = null)
    {
        return $this->setRelation(Relation::TO_ONE, $model, $local_key, $foreign_key);
    }

    /**
     * When the model has many relations.
     * 
     * @param  string      $model       The model class
     * @param  string|null $foreign_key The relation foreign key
     * @param  string|null $local_key   The relation local key
     * @return \Core\Pdo\Relation
     */
    protected function hasMany($model, $foreign_key = null, $local_key = null)
    {
        return $this->setRelation(Relation::TO_MANY, $model, $local_key, $foreign_key);
    }

    /**
     * Sets the `Relation` by type.
     * 
     * @param  int         $type        The relation type `Relation::TO_ONE` or `Relation::TO_MANY`
     * @param  string      $model_class The model class
     * @param  string|null $local_key   The relation local key
     * @param  string|null $foreign_key The relation foreign key
     * @return \Core\Pdo\Relation
     */
    protected function setRelation($type, $model_class, $local_key = null, $foreign_key = null)
    {
        $rel_key = snake_case(isset($local_key) ? "{$model_class}_{$local_key}" : $model_class);
        if (isset($this->relations[$rel_key])) {
            return $this->relations[$rel_key];
        }

        $foreign_model = new $model_class($this->db);
        $relation = new Relation($type, $foreign_model, $this, $foreign_key, $local_key);
        $this->relations[$rel_key] = $relation;
        return $relation;
    }

    /**
     * Fetch models from prepared statement.
     * 
     * @return \Core\Collection
     */
    public function get()
    {
        $query = $this->query();

        $relations = [];
        foreach ($this->with as $with) {
            $relation = $this->invokeRelation($with);
            if (null === $relation) {
                continue;
            }
            $relations[$with] = $relation;
        }

        foreach ($relations as $rel => $relation) {
            if ($relation->type() !== Relation::TO_ONE) {
                continue;
            }

            $model_table = $relation->joinTable();
            $separator = "{%$rel%}";
            $query
                ->select("'' as '$separator'", [$model_table, '*'])
                ->leftJoin($model_table, $relation->joinKey(), $relation->localKey());
        }
        $items = $query->run($this->db);

        $ids = $items->map(function ($item) {
            return $item->{$this->primaryKey};
        })->all();

        $joins = [];
        foreach ($relations as $rel => $relation) {
            if ($relation->type() !== Relation::TO_MANY || empty($ids)) {
                continue;
            }
            $model_table = $relation->joinTable();
            $results = $relation->call($this->db, $ids);
            $collection = $results instanceof Collection ? $results : collect();
            $k = $relation->hasPivot() ? $relation->localPivot() : $relation->joinKey();
            $dict = [];
            foreach ($collection as $row) {
                $key = $row->{$k};
                if (!isset($dict[$key])) {
                    $dict[$key] = [];
                }
                $dict[$key][] = $row;
            }
            $joins[$rel] = $dict;
        }

        $entities = [];
        foreach ($items as $item) {
            foreach ($relations as $rel => $relation) {
                $t = $relation->type();
                switch ($t) {
                    case Relation::TO_ONE: {
                            $model = $relation->joinClass();
                            $attributes = $item->getJoinValues($rel);
                            $with_model = new $model($this->db, $attributes);
                            $item->{$rel} = null !== $with_model->id() ? $with_model : null;
                            break;
                        }
                    case Relation::TO_MANY: {
                            $dict = isset($joins[$rel]) ? $joins[$rel] : [];
                            $lk = $relation->localKey();
                            $lookup = $item->{$lk};
                            $item->{$rel} = collect(isset($dict[$lookup]) ? $dict[$lookup] : []);
                            break;
                        }
                }
            }
            $entities[] = new static($this->db, $item);
        }

        return collect($entities);
    }

    /**
     * Saves the current model to database.
     * 
     * @return void
     * @throws \Core\Pdo\DbQueryException
     */
    public function save()
    {
        $primary_key = $this->primaryKey;
        $query = SqlQuery::table($this->table());

        $is_persistent = $this->isPersistent();
        $cmd = $is_persistent ? 'UPDATE' : 'INSERT';
        $values = [];

        if ($is_persistent) {
            if (null !== static::UPDATED_AT) {
                $this->__set(static::UPDATED_AT, current_timestamp());
            }

            $values = $this->willSave($cmd, $this->changes());
            $query
                ->update($values)
                ->where([$this->table, $primary_key], '=', $this->$primary_key);
        } else {
            if (null !== static::CREATED_AT) {
                $this->__set(static::CREATED_AT, current_timestamp());
            }
            $values = $this->willSave($cmd, $this->attributes);
            $query->insert($values);
        }

        if (!empty($values)) {
            try {
                $query->run($this->db);
                $inserted_id = $query->returnedId();
                if (isset($inserted_id)) {
                    $this->attributes[$this->primaryKey] = $this->transform('set', $this->primaryKey, $inserted_id);
                }
                $this->didSave($cmd, $values);
                $this->updateTouches();
            } catch (DbQueryException $e) {
                Logger::print(LogLevel::ERROR, '[' . static::class . ']: ' . SqlQuery::combine($query->get(), $query->params()) . ' | ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                throw $e;
            }
        }

        if (count($this->toMany) > 0 && $this->isPersistent()) {
            try {
                foreach ($this->toMany as $element) {
                    $this->setToManyRelations($element[0], $element[1], $element[2]);
                }
                $this->toMany = [];
                $this->updateTouches();
            } catch (Exception $e) {
                throw $e;
            }
        }
    }

    /**
     * Prepares and filters the attributes before saving.
     * 
     * @param  string $cmd The query command, `INSERT` or `UPDATE`
     * @param  array $attributes The unfiltered attributes
     * @return array
     * @throws \Core\Pdo\DbQueryException
     */
    protected function willSave($cmd, $attributes)
    {
        $schema = $this->db()->config()['schema'];
        $fillable = isset($schema[$this->table]) ? array_keys($schema[$this->table]) : [];

        $filtered = [];
        if ($cmd === 'INSERT') {
            $attributes[$this->primaryKey] = null;
            $filtered[$this->primaryKey] = null;
        }
        foreach ($attributes as $key => $value) {
            if (!in_array($key, $fillable)) {
                continue;
            }
            $filtered[$key] = $value;
        }
        return $filtered;
    }

    /**
     * Cleans up after saving, if needed.
     * 
     * @param  string $cmd The query command, `INSERT` or `UPDATE`
     * @param  array $attributes The attributes changed after saving
     * @return void
     */
    protected function didSave($cmd, $attributes)
    {
        $this->changes = [];
    }

    /**
     * Deletes the current model from database, or marks it as deleted.
     * 
     * @param  bool $coerced Forces the deletion
     * @return bool
     */
    public function delete($coerced = false)
    {
        if (!$this->isPersistent()) {
            $this->attributes = [];
            $this->changes = [];
            return false;
        }

        $query = SqlQuery::table($this->table);
        if (null !== static::DELETED_AT && !$coerced) {
            $query
                ->update([static::DELETED_AT => current_timestamp()])
                ->where($this->primaryKey, '=', $this->id())
                ->run($this->db);
            $this->updateTouches();
            return true;
        }

        $query->delete()
            ->where($this->primaryKey, '=', $this->id())
            ->run($this->db);
        $this->updateTouches();
        return true;
    }

    /**
     * Restores the current model and marks it as undeleted.
     * 
     * @return bool
     */
    public function restore()
    {
        if (!$this->isPersistent() || null === static::DELETED_AT) {
            return false;
        }

        SqlQuery::table($this->table)
            ->update([static::DELETED_AT => null])
            ->where($this->primaryKey, '=', $this->id())
            ->run($this->db);

        $this->updateTouches();
    }

    /**
     * Sets the model attributes.
     * 
     * @return void
     */
    public function setup($values)
    {
        $this->casts[$this->primaryKey] = $this->keyType;

        $attributes = $values instanceof JsonSerializable ? $values->jsonSerialize() : (is_array($values) ? $values : []);
        foreach ($attributes as $key => $value) {
            $this->__set($key, $value);
        }

        $this->changes = [];
    }

    /**
     * Refresh the object attributes and relationships.
     */
    public function refresh()
    {
        if (!$this->isPersistent()) return;

        $from_db = static::find($this->db, $this->id());
        $attributes = $from_db instanceof Model ? $from_db->jsonSerialize() : [];
        foreach ($attributes as $key => $val) {
            $this->attributes[$key] = $val;
        }

        foreach ($this->relations as $rel) {
            if ($rel instanceof Relation) {
                $rel->refresh();
            }
        }
    }

    /**
     * Sets any attribute by key.
     * 
     * @param  string $key
     * @param  mixed  $value
     * @return void
     */
    public function set($key, $value)
    {
        if ($key === $this->primaryKey()) {
            return;
        }

        if (method_exists($this, $key)) {
            $method = call_user_func([$this, $key]);
            if ($method instanceof Attribute) {
                $method->set($key, $value);
            } elseif ($method instanceof Relation) {
                $type = $method->type();
                switch ($type) {
                    case Relation::TO_ONE: {
                            $this->setToOneRelation($method, $key, $value);
                            break;
                        }
                    case Relation::TO_MANY: {
                            $this->toMany[] = [$method, $key, $value];
                            break;
                        }
                }
            }
            return;
        }

        $this->__set($key, $value);
    }

    /**
     * Sets the One-To-Many relationship.
     * 
     * @param  \Core\Pdo\Relation $relation
     * @param  string $key
     * @param  \Core\Pdo\Model|string|int $value
     * @return void
     */
    public function setToOneRelation(Relation $relation, $key, $value)
    {
        $name = $relation->localKey();
        $this->__set($name, $value instanceof Model ? $value->id() : (is_primitive($value) ? $value : null));
    }

    /**
     * Sets the Many-To-Many relationship.
     * 
     * @param  \Core\Pdo\Relation $relation
     * @param  string $key
     * @param  mixed $value
     * @return void
     */
    public function setToManyRelations(Relation $relation, $key, $value) {}

    /**
     * Updates the relationships when saved.
     * 
     * @return void
     */
    protected function updateTouches()
    {
        foreach ($this->touches as $touch) {
            $model = $this->{$touch};
            if (!($model instanceof Model)) {
                continue;
            }
            if ($model->isTimestamped()) {
                $model->__set($model::UPDATED_AT, current_timestamp());
                $model->save();
            }
        }
    }

    /**
     * The select statements associated with the query.
     *
     * @param  string $rel The name of the relation
     * @return \Core\Pdo\Relation|null
     */
    protected function invokeRelation($rel)
    {
        if (!method_exists($this, $rel)) {
            return null;
        }

        $relation = call_user_func([$this, $rel]);
        return $relation instanceof Relation ? $relation : null;
    }

    /**
     * Gets the casting type for a specific attribute from the $casts attributes.
     * 
     * @param  string $key
     * @return string
     */
    public function castingTypeFor($key)
    {
        return get_value($key, $this->casts, '');
    }

    /**
     * Transforms the underlying data into the desired cast attribute.
     * 
     * @param  string $fn Either `get` or `set`
     * @param  string $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function transform($fn, $key, $value)
    {
        $cast = get_value($key, $this->casts, '');
        if (is_null($value)) {
            return $cast === 'bool' || $cast === 'boolean' ? false : null;
        }

        $types = explode(':', $cast);
        $c = count($types);
        $type = $c > 0 ? $types[0] : '';
        $param = $c > 1 ? $types[1] : '';

        switch ($type) {
            case 'int':
            case 'integer': {
                    return intval($value);
                }
            case 'bool':
            case 'boolean': {
                    return boolval($value);
                }
            case 'float':
            case 'double': {
                    return doubleval($value);
                }
            case 'decimal': {
                    return round(doubleval($value), intval($param));
                }
            case 'date': {
                    $format = empty($param) ? 'Y-m-d' : $param;
                    $timestamp = is_numeric($value) ? $value : strtotime($value);
                    return $timestamp !== false ? date($format, $timestamp) : null;
                }
            case 'datew3c': {
                    $timestamp = is_numeric($value) ? $value : strtotime($value);
                    return $timestamp !== false ? date(DATE_W3C, $timestamp) : null;
                }
            case 'timestamp': {
                    if ($fn === 'get') {
                        $timestamp = is_numeric($value) ? $value : strtotime($value);
                        return $timestamp !== false ? $timestamp : null;
                    }
                    return is_numeric($value) ? date('Y-m-d H:i:s', $value) : $value;
                }
            case 'ip': {
                    $is_binary = preg_match('/[^\x20-\x7E]/', $value);
                    if ($fn === 'get') {
                        $ip_text = $is_binary ? inet_ntop($value) : false;
                        return $ip_text !== false ? $ip_text : null;
                    }
                    $binary_ip = $is_binary ? $value : inet_pton($value);
                    return $binary_ip;
                }
            case 'array': {
                    if ($fn === 'get') {
                        return is_string($value) && strlen($value) > 0 ? json_decode($value, true) : null;
                    }
                    return is_string($value) ? $value : (is_array($value) || $value instanceof JsonSerializable ? json_encode($value) : null);
                }
            case 'object': {
                    if ($fn === 'get') {
                        $obj = new StdObject();
                        $json = is_string($value) && strlen($value) > 0 ? json_decode($value, true) : [];
                        foreach ($json as $k => $v) {
                            if (is_string($k)) {
                                $obj->{$k} = $v;
                            }
                        };
                        return $obj;
                    }
                    return is_string($value) ? $value : ($value instanceof JsonSerializable ? json_encode($value) : null);
                }
            case 'collection': {
                    if ($fn === 'get') {
                        return is_string($value) && strlen($value) > 0 ? collect(json_decode($value, true)) : collect();
                    }
                    return is_string($value) ? $value : ($value instanceof JsonSerializable ? json_encode($value) : null);
                }
            case 'string': {
                    return strval($value);
                }
            default: {
                    if (strlen($cast) > 0) {
                        $cast_attr = class_exists($cast) ? new $cast : null;
                        if ($cast_attr instanceof CastAttribute) {
                            return $cast_attr->{$fn}($this, $key, $value, $this->attributes);
                        }
                    }
                    return $value;
                }
        }
    }

    /**
     * Triggered when invoking inaccessible methods in an object context.
     * 
     * @param  string $name
     * @param  array  $arguments
     * @return void|self
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->query(), $name)) {
            call_user_func_array([$this->query, $name], $arguments);
            return $this;
        }
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
            return $this->transform('get', $name, $this->attributes[$name]);
        } elseif (method_exists($this, $name)) {
            $method = call_user_func([$this, $name]);
            if ($method instanceof Attribute) {
                return $method->get($name);
            } elseif ($method instanceof Relation) {
                if ($this->isPersistent()) {
                    return $method->call($this->db, [$this->id()]);
                }
            }
            return null;
        }

        return property_exists($this, $name) ? $this->transform('get', $name, $this->$name) : null;
    }

    /**
     * Runs when writing data to inaccessible (protected or private) or non-existing properties.
     * 
     * @param  string $name The name of the property
     * @param  mixed $value
     * @return void
     */
    public function __set($name, $value)
    {
        $key = !method_exists($this, $name) && isset($this->mapping[$name]) ? $this->mapping[$name] : $name;
        if (!is_array($this->attributes)) {
            $this->attributes = [];
        }
        if (!is_array($this->changes)) {
            $this->changes = [];
        }

        $v = $this->transform('set', $key, $value);
        if (array_key_exists($key, $this->attributes) && $this->attributes[$key] !== $v) {
            $this->changes[$key] = $this->attributes[$key];
        }
        $this->attributes[$key] = $v;
    }

    /**
     * Calls `isset()` or `empty()` on inaccessible (protected or private) or non-existing properties.
     * 
     * @return bool
     */
    public function __isset($name)
    {
        return array_key_exists($name, $this->attributes) ? true : isset($this->$name);
    }

    /**
     * Calls `unset()` on inaccessible (protected or private) or non-existing properties.
     * 
     * @return bool
     */
    public function __unset($name)
    {
        if (array_key_exists($name, $this->attributes)) {
            unset($this->attributes[$name]);
        }

        unset($this->$name);
    }

    /**
     * Specifies data which should be serialized to JSON. 
     * 
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        $attributes = [];
        foreach ($this->attributes as $key => $value) {
            if (in_array($key, $this->hidden)) {
                continue;
            }
            $attributes[$key] = $this->transform('get', $key, $value);
        }
        return $attributes;
    }

    /**
     * String representation of the model. 
     * 
     * @return string
     */
    public function serialize(): string
    {
        $encode = json_encode($this->jsonSerialize());
        return $encode !== false ? $encode : '';
    }

    /**
     * Constructs the model from array. 
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
     * Data representation of the model. 
     * 
     * @return mixed
     */
    public function __serialize()
    {
        return $this->jsonSerialize();
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
