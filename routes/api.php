<?php

use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\TableController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Restaurant Reservation API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api (configured in bootstrap/app.php).
| Version prefix v1 is applied here to future-proof the API.
|
*/

Route::prefix('v1')->group(function () {

    // ── Tables (staff-facing) ─────────────────────────────────────────────
    Route::apiResource('tables', TableController::class)
         ->only(['index', 'show', 'store', 'update', 'destroy']);

    Route::patch('tables/{table}/inactivate', [TableController::class, 'inactivate'])
         ->name('tables.inactivate');

    // ── Availability (public) ─────────────────────────────────────────────
    Route::get('availability', [AvailabilityController::class, 'index'])
         ->name('availability.index');

    // ── Reservations (public) ─────────────────────────────────────────────
    Route::post('reservations', [ReservationController::class, 'store'])
         ->name('reservations.store');

    Route::get('reservations/{referenceCode}', [ReservationController::class, 'show'])
         ->name('reservations.show');

    Route::delete('reservations/{referenceCode}', [ReservationController::class, 'cancel'])
         ->name('reservations.cancel');
});
