<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ReservationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\BookTableRequest;
use App\Http\Requests\CancelReservationRequest;
use App\Http\Requests\CopyReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservationService
    ) {}

    /**
     * POST /api/reservations
     *
     * Book a table. Supports both explicit table selection and smart assignment.
     */
    public function store(BookTableRequest $request): JsonResponse
    {
        try {
            $reservation = $this->reservationService->book($request->validated());
        } catch (ReservationException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return (new ReservationResource($reservation->load('table')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * GET /api/reservations/{referenceCode}
     *
     * Fetch a reservation by its unique reference code.
     */
    public function show(string $referenceCode): JsonResponse
    {
        $reservation = Reservation::with('table')
            ->where('reference_code', strtoupper($referenceCode))
            ->firstOrFail();

        return (new ReservationResource($reservation))->response();
    }

    /**
     * DELETE /api/reservations/{referenceCode}
     *
     * Cancel a reservation (subject to cancellation policy).
     */
    public function cancel(CancelReservationRequest $request, string $referenceCode): JsonResponse
    {
        $reservation = Reservation::with('table')
            ->where('reference_code', strtoupper($referenceCode))
            ->firstOrFail();

        try {
            $reservation = $this->reservationService->cancel(
                $reservation,
                $request->input('reason', '')
            );
        } catch (ReservationException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return (new ReservationResource($reservation))->response();
    }

    /**
     * POST /api/reservations/{referenceCode}/copy
     *
     * Copy a reservation to another similar table on the same date and slot.
     * The original reservation will be cancelled after copying.
     */
    public function copy(CopyReservationRequest $request, string $referenceCode): JsonResponse
    {
        $reservation = Reservation::with('table')
            ->where('reference_code', strtoupper($referenceCode))
            ->firstOrFail();

        try {
            $newReservation = $this->reservationService->copy(
                $reservation,
                $request->input('table_id')
            );
        } catch (ReservationException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return (new ReservationResource($newReservation))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
