<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['service' => 'CV Scoring System', 'status' => 'running']);
});
