<?php

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ReservationException extends RuntimeException
{
    private int $statusCode;

    private function __construct(string $message, int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    // ─── Named constructors ───────────────────────────────────────────────────

    public static function tableCapacityExceeded(int $capacity, int $guestCount): self
    {
        return new self(
            "Table capacity ({$capacity}) is insufficient for {$guestCount} guests.",
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    public static function tableAlreadyBooked(): self
    {
        return new self(
            'This table is already booked for the selected date and time slot.',
            Response::HTTP_CONFLICT
        );
    }

    public static function noTableAvailable(int $guestCount): self
    {
        return new self(
            "No available table found for {$guestCount} guests at the requested date and time.",
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    public static function cancellationNotAllowed(int $cutoffHours): self
    {
        return new self(
            "Reservations cannot be cancelled within {$cutoffHours} hour(s) of the booking time.",
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    public static function dateInPast(): self
    {
        return new self('Reservation date cannot be in the past.');
    }

    public static function dateTooFarAhead(int $maxDays): self
    {
        return new self("Reservations can only be made up to {$maxDays} days in advance.");
    }

    public static function invalidSlot(string $slotStart): self
    {
        return new self("'{$slotStart}' is not a valid time slot.");
    }
}
