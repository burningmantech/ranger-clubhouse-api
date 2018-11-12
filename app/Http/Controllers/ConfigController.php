<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

require_once base_path('config/clubhouse.php');

class ConfigController extends Controller
{
    public function show() {
        return response()->json(config('client'));
    }
}
