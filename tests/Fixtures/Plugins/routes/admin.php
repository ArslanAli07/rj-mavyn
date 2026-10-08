<?php

use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Plugins\FixtureController;

Route::get('probe', FixtureController::class)->name('probe');
Route::post('probe', FixtureController::class)->name('mutate');
