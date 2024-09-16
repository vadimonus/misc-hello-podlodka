<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    session()->put('clicks', session()->get('clicks', 0) + 1);
    return view('welcome');
});
