<?php

use Illuminate\Support\Facades\Route;
use Pecotamic\Antispam\Http\Controllers\AssetController;
use Pecotamic\Antispam\Http\Controllers\PixelController;
use Pecotamic\Antispam\Http\Controllers\ProofController;

Route::prefix('!/pecotamic-antispam')->name('pecotamic.antispam.')->group(function () {
    Route::get('proof', ProofController::class)->name('proof');
    Route::get('p.png', PixelController::class)->name('pixel');

    // The fingerprint sits in the path so the relative imports inside the
    // modules inherit it and cannot resolve to a stale copy.
    Route::get('js/{fingerprint}/{file}', AssetController::class)
        ->where('file', '[A-Za-z0-9_.-]+\.js')
        ->name('asset');
});
