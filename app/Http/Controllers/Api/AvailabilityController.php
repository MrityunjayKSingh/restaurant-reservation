<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ReservationException;
use App\Http\Controllers\Controller;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservationService
    ) {}

    /**
     * GET /api/availability?date=YYYY-MM-DD&guest_count=2
     *
     * Returns all time slots for the given date with available table counts.
     * Optionally filters by guest_count to show only compatible tables.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date'        => ['required', 'date', 'date_format:Y-m-d'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        try {
            $slots = $this->reservationService->getAvailability(
                $request->input('date'),
                $request->integer('guest_count') ?: null,
            );
        } catch (ReservationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }

        return response()->json([
            'data' => $slots,
            'meta' => [
                'date'        => $request->input('date'),
                'guest_count' => $request->integer('guest_count') ?: null,
                'total_slots' => count($slots),
            ],
        ]);
    }
}
