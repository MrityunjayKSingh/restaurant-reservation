<?php

namespace App\Services;

use App\Models\Table;
use Illuminate\Support\Collection;

class TableAssignmentService
{
    /**
     * Find the smallest available table that fits the party.
     *
     * Strategy:
     *  1. Filter active tables with sufficient capacity.
     *  2. Exclude tables already booked for this date + slot.
     *  3. Among remaining tables, pick the one with the smallest capacity
     *     (and lowest table_number as a tiebreaker) — minimising waste.
     */
    public function findBestTable(
        int    $guestCount,
        string $date,
        string $slotStart,
        ?string $preferredLocation = null
    ): ?Table {
        $query = Table::active()
            ->withMinCapacity($guestCount)
            ->whereDoesntHave('reservations', function ($q) use ($date, $slotStart) {
                $q->where('reservation_date', $date)
                  ->where('slot_start', $slotStart)
                  ->where('status', 'confirmed');
            })
            ->orderBy('capacity')
            ->orderBy('table_number');

        if ($preferredLocation) {
            // Try preferred location first; fall back to any if none found
            $table = (clone $query)->byLocation($preferredLocation)->first();

            return $table ?? $query->first();
        }

        return $query->first();
    }

    /**
     * Get all available tables for a given date / slot with their details.
     *
     * @return Collection<int, Table>
     */
    public function getAvailableTables(string $date, string $slotStart): Collection
    {
        return Table::active()
            ->with(['reservations' => function ($q) use ($date, $slotStart) {
                $q->where('reservation_date', $date)
                  ->where('slot_start', $slotStart)
                  ->where('status', 'confirmed');
            }])
            ->get()
            ->filter(fn (Table $t) => $t->reservations->isEmpty())
            ->values();
    }
}
