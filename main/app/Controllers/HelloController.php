<?php

namespace App\Controllers;

use Core\Controller;

/**
 * HelloController
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class HelloController extends Controller
{
    public function wave($name)
    {
        $to = is_string($name) ? ucfirst(strtolower($name)) : 'World';
        return response()->text("Hello, $to!");
    }
}
