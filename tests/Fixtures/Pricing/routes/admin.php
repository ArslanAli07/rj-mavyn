<?php

use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Pricing\PricingController;

Route::get('/', [PricingController::class, 'index'])->name('index');
Route::post('/prices', [PricingController::class, 'save'])->name('save');
