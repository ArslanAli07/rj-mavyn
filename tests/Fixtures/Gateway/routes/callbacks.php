<?php

use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Gateway\CallbackController;

Route::post('events', CallbackController::class)->name('events');
