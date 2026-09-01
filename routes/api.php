<?php

use Illuminate\Support\Facades\Route;

Route::get('/user', function () {
    return request()->user();
})->middleware('auth:sanctum');
