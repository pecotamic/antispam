<?php

use Illuminate\Support\Facades\Route;
use Pecotamic\Antispam\Http\Controllers\ProofController;

Route::get('!/pecotamic-antispam/proof', ProofController::class)
    ->name('pecotamic.antispam.proof');
