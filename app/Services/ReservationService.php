<?php

namespace App\Services;

use App\Exceptions\ReservationException;
use App\Models\Reservation;
use App\Models\Table;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    public function __construct(
        private readonly SlotGeneratorService  $slotGenerator,
        private readonly TableAssignmentService $tableAssignment,
    ) {}

    // ─── Availability ─────────────────────────────────────────────────────────

    /**
     * Return available slots for a date, each decorated with available tables.
     */
    public function getAvailability(string $date, ?int $guestCount = null): array
    {
        $carbonDate = Carbon::parse($date);

        $this->assertDateIsBookable($carbonDate);

        $slots = $this->slotGenerator->generateForDate($carbonDate);

        return $slots->map(function (array $slot) use ($date, $guestCount) {
            $tables = $this->tableAssignment->getAvailableTables($date, $slot['slot_start']);

            if ($guestCount !== null) {
                $tables = $tables->filter(fn (Table $t) => $t->capacity >= $guestCount)->values();
            }

            return array_merge($slot, [
                'available_tables' => $tables->map(fn (Table $t) => [
                    'id'           => $t->id,
                    'table_number' => $t->table_number,
                    'capacity'     => $t->capacity,
                    'location'     => $t->location,
                ])->values(),
                'available_count' => $tables->count(),
            ]);
        })->values()->all();
    }

    // ─── Booking ──────────────────────────────────────────────────────────────

    /**
     * Create a reservation.
     *
     * If table_id is provided, book that specific table (after validation).
     * Otherwise, invoke smart assignment to find the best table.
     */
    public function book(array $data): Reservation
    {
        $date      = $data['reservation_date'];
        $slotStart = $data['slot_start'];
        $slotEnd   = $this->slotGenerator->resolveSlotEnd($slotStart);

        $this->assertDateIsBookable(Carbon::parse($date));
        $this->assertSlotIsValid($slotStart);

        return DB::transaction(function () use ($data, $date, $slotStart, $slotEnd) {

            $table = isset($data['table_id'])
                ? $this->resolveExplicitTable($data['table_id'], $data['guest_count'], $date, $slotStart)
                : $this->resolveSmartTable($data['guest_count'], $date, $slotStart, $data['preferred_location'] ?? null);

            return Reservation::create([
                'table_id'         => $table->id,
                'customer_name'    => $data['customer_name'],
                'customer_email'   => $data['customer_email'],
                'customer_phone'   => $data['customer_phone'],
                'guest_count'      => $data['guest_count'],
                'special_requests' => $data['special_requests'] ?? null,
                'reservation_date' => $date,
                'slot_start'       => $slotStart,
                'slot_end'         => $slotEnd,
                'status'           => 'confirmed',
            ]);
        });
    }

    // ─── Cancellation ─────────────────────────────────────────────────────────

    public function cancel(Reservation $reservation, string $reason = ''): Reservation
    {
        if (! $reservation->isCancellable()) {
            throw ReservationException::cancellationNotAllowed(
                config('reservation.cancellation_cutoff_hours', 2)
            );
        }

        $reservation->cancel($reason);

        return $reservation->fresh('table');
    }

    // ─── Copy Reservation ─────────────────────────────────────────────────────

    /**
     * Copy a reservation to another similar table on the same date and slot.
     * After successful copy, the original reservation is cancelled.
     */
    public function copy(Reservation $reservation, int $newTableId): Reservation
    {
        return DB::transaction(function () use ($reservation, $newTableId) {
            $newTable = Table::active()->findOrFail($newTableId);

            // Ensure the new table has the same capacity as the original
            if ($newTable->capacity !== $reservation->table->capacity) {
                throw ReservationException::tableCapacityExceeded(
                    $newTable->capacity,
                    $reservation->guest_count
                );
            }

            // Ensure the new table is available on the same date/slot
            if (! $newTable->isAvailableForSlot($reservation->reservation_date, $reservation->slot_start)) {
                throw ReservationException::tableAlreadyBooked();
            }

            // Create new reservation with same details
            $newReservation = Reservation::create([
                'table_id'         => $newTable->id,
                'customer_name'    => $reservation->customer_name,
                'customer_email'   => $reservation->customer_email,
                'customer_phone'   => $reservation->customer_phone,
                'guest_count'      => $reservation->guest_count,
                'special_requests' => $reservation->special_requests,
                'reservation_date' => $reservation->reservation_date,
                'slot_start'       => $reservation->slot_start,
                'slot_end'         => $reservation->slot_end,
                'status'           => 'confirmed',
            ]);

            // Cancel the original reservation
            $reservation->cancel('Moved to another table');

            return $newReservation->load('table');
        });
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function resolveExplicitTable(
        int    $tableId,
        int    $guestCount,
        string $date,
        string $slotStart
    ): Table {
        $table = Table::active()->findOrFail($tableId);

        if ($table->capacity < $guestCount) {
            throw ReservationException::tableCapacityExceeded($table->capacity, $guestCount);
        }

        if (! $table->isAvailableForSlot($date, $slotStart)) {
            throw ReservationException::tableAlreadyBooked();
        }

        return $table;
    }

    private function resolveSmartTable(
        int    $guestCount,
        string $date,
        string $slotStart,
        ?string $preferredLocation
    ): Table {
        $table = $this->tableAssignment->findBestTable(
            $guestCount,
            $date,
            $slotStart,
            $preferredLocation
        );

        if (! $table) {
            throw ReservationException::noTableAvailable($guestCount);
        }

        return $table;
    }

    private function assertDateIsBookable(Carbon $date): void
    {
        $maxAdvance = config('reservation.max_advance_days', 30);
        $minAdvance = config('reservation.min_advance_hours', 2);

        if ($date->isPast() && ! $date->isToday()) {
            throw ReservationException::dateInPast();
        }

        if ($date->diffInDays(now(), false) < -$maxAdvance) {
            throw ReservationException::dateTooFarAhead($maxAdvance);
        }
    }

    private function assertSlotIsValid(string $slotStart): void
    {
        if (! $this->slotGenerator->isValidSlot($slotStart)) {
            throw ReservationException::invalidSlot($slotStart);
        }
    }
}
