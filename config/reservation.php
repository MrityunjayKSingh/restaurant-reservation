<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Time Slot Configuration
    |--------------------------------------------------------------------------
    */
    'slot_duration_minutes' => (int) env('RESERVATION_SLOT_DURATION_MINUTES', 90),

    /*
    |--------------------------------------------------------------------------
    | Booking Window
    |--------------------------------------------------------------------------
    */
    'max_advance_days'  => (int) env('RESERVATION_MAX_ADVANCE_DAYS', 30),
    'min_advance_hours' => (int) env('RESERVATION_MIN_ADVANCE_HOURS', 2),

    /*
    |--------------------------------------------------------------------------
    | Cancellation Policy
    |--------------------------------------------------------------------------
    | Reservations cannot be cancelled within this many hours of the slot.
    */
    'cancellation_cutoff_hours' => (int) env('CANCELLATION_CUTOFF_HOURS', 2),

    /*
    |--------------------------------------------------------------------------
    | Operating Hours
    |--------------------------------------------------------------------------
    */
    'open_time'  => env('RESTAURANT_OPEN_TIME', '11:00'),
    'close_time' => env('RESTAURANT_CLOSE_TIME', '22:00'),

    /*
    |--------------------------------------------------------------------------
    | Reservation Statuses
    |--------------------------------------------------------------------------
    */
    'statuses' => [
        'confirmed' => 'confirmed',
        'cancelled' => 'cancelled',
        'completed' => 'completed',
        'no_show'   => 'no_show',
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Locations
    |--------------------------------------------------------------------------
    */
    'locations' => ['indoor', 'outdoor'],

];
