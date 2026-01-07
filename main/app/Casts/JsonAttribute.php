<?php

namespace App\Casts;

use Core\Pdo\CastAttribute;
use Core\Pdo\Model;
use JsonSerializable;

/**
 * JsonAttribute
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class JsonAttribute implements CastAttribute
{
    public function get(Model $model, $key, $value, $attributes)
    {
        return is_string($value) && strlen($value) > 2 ? json_decode(trim($value), true) : null;
    }

    public function set(Model $model, $key, $value, $attributes)
    {
        $old_value = $model->$key;
        $old_json = is_string($old_value) ? json_decode($old_value, true) : $old_value;
        $new_json = is_string($value) ? json_decode($value, true) : $value;
        $merge_value = is_array($old_json) && is_array($new_json) ? array_merge($old_json, $new_json) : $new_json;
        return is_array($merge_value) || $merge_value instanceof JsonSerializable ? json_encode($merge_value) : null;
    }
}
