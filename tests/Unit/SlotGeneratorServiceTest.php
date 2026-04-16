<?php

namespace Tests\Unit;

use App\Services\SlotGeneratorService;
use Carbon\Carbon;
use Tests\TestCase;

class SlotGeneratorServiceTest extends TestCase
{
    private SlotGeneratorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SlotGeneratorService();
    }

    public function test_generates_slots_covering_full_operating_hours(): void
    {
        $slots = $this->service->generateForDate(Carbon::today());

        $this->assertNotEmpty($slots);

        $openTime  = config('reservation.open_time', '11:00');
        $closeTime = config('reservation.close_time', '22:00');

        // First slot starts at opening
        $this->assertEquals($openTime, $slots->first()['slot_start']);

        // Last slot ends at or before closing
        $lastSlotEnd = $slots->last()['slot_end'];
        $this->assertLessThanOrEqual($closeTime, $lastSlotEnd);
    }

    public function test_slot_duration_matches_config(): void
    {
        $duration = config('reservation.slot_duration_minutes', 90);
        $slots    = $this->service->generateForDate(Carbon::today());

        foreach ($slots as $slot) {
            $start    = Carbon::parse($slot['slot_start']);
            $end      = Carbon::parse($slot['slot_end']);
            $diff     = $start->diffInMinutes($end);
            $this->assertEquals($duration, $diff);
        }
    }

    public function test_slots_do_not_overlap(): void
    {
        $slots = $this->service->generateForDate(Carbon::today());

        for ($i = 0; $i < $slots->count() - 1; $i++) {
            $currentEnd  = $slots[$i]['slot_end'];
            $nextStart   = $slots[$i + 1]['slot_start'];
            $this->assertEquals($currentEnd, $nextStart, 'Slots should be contiguous with no overlap');
        }
    }

    public function test_resolve_slot_end_adds_duration(): void
    {
        $end = $this->service->resolveSlotEnd('14:00');
        $this->assertEquals('15:30', $end);
    }

    public function test_is_valid_slot_returns_true_for_valid_start(): void
    {
        $firstSlot = $this->service->generateForDate(Carbon::today())->first()['slot_start'];

        $this->assertTrue($this->service->isValidSlot($firstSlot));
    }

    public function test_is_valid_slot_returns_false_for_arbitrary_time(): void
    {
        $this->assertFalse($this->service->isValidSlot('10:47'));
        $this->assertFalse($this->service->isValidSlot('23:00'));
    }

    public function test_slot_count_is_consistent_across_dates(): void
    {
        $count1 = $this->service->generateForDate(Carbon::today())->count();
        $count2 = $this->service->generateForDate(Carbon::today()->addDays(10))->count();

        $this->assertEquals($count1, $count2);
    }
}
