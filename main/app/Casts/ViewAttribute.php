<?php

namespace App\Casts;

use Core\Pdo\CastAttribute;
use Core\Pdo\Model;
use Core\View;

/**
 * ViewAttribute
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class ViewAttribute implements CastAttribute
{
    public function get(Model $model, $key, $value, $attributes)
    {
        if (isset($value) && strlen($value) > 0) {
            $view = new View("{$model->storage}/$value");
            return $view->render();
        }
        return null;
    }

    public function set(Model $model, $key, $value, $attributes)
    {
        $v = isset($attributes[$key]) ? $attributes[$key] : $value;
        return is_string($v) ? $v : null;
    }
}
