<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class SlotGeneratorService
{
    private int $slotDurationMinutes;
    private string $openTime;
    private string $closeTime;

    public function __construct()
    {
        $this->slotDurationMinutes = config('reservation.slot_duration_minutes', 90);
        $this->openTime            = config('reservation.open_time', '11:00');
        $this->closeTime           = config('reservation.close_time', '22:00');
    }

    /**
     * Generate all time slots for a given date.
     *
     * @return Collection<int, array{slot_start: string, slot_end: string, label: string}>
     */
    public function generateForDate(Carbon $date): Collection
    {
        $slots  = collect();
        $cursor = Carbon::parse($date->format('Y-m-d') . ' ' . $this->openTime);
        $close  = Carbon::parse($date->format('Y-m-d') . ' ' . $this->closeTime);

        // Last slot must start early enough for guests to finish before closing
        $lastStart = $close->copy()->subMinutes($this->slotDurationMinutes);

        while ($cursor->lessThanOrEqualTo($lastStart)) {
            $end = $cursor->copy()->addMinutes($this->slotDurationMinutes);

            $slots->push([
                'slot_start' => $cursor->format('H:i'),
                'slot_end'   => $end->format('H:i'),
                'label'      => $cursor->format('h:i A') . ' – ' . $end->format('h:i A'),
            ]);

            $cursor->addMinutes($this->slotDurationMinutes);
        }

        return $slots;
    }

    /**
     * Resolve slot_end from a slot_start string.
     */
    public function resolveSlotEnd(string $slotStart): string
    {
        return Carbon::parse($slotStart)
            ->addMinutes($this->slotDurationMinutes)
            ->format('H:i');
    }

    /**
     * Check whether a given slot_start is a valid slot boundary for the day.
     */
    public function isValidSlot(string $slotStart): bool
    {
        $date = Carbon::today();

        return $this->generateForDate($date)
            ->pluck('slot_start')
            ->contains($slotStart);
    }
}
