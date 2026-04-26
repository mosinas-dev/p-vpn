<?php

use App\Http\Controllers\Webhooks\CardlinkController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/cardlink', [CardlinkController::class, 'handle'])->name('webhooks.cardlink');
