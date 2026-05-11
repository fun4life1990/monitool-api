<?php

use Illuminate\Support\Facades\Route;

Route::get('/swagger', function () {
    abort_unless(config('app.swagger.enable') === true, 404);

    return view('swagger', ['schemaUrl' => '/openapi.yaml']);
});
