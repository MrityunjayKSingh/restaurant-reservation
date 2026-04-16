<?php

namespace Tests\Unit;

use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_code_is_auto_generated_on_create(): void
    {
        $reservation = Reservation::factory()
            ->for(Table::factory())
            ->create();

        $this->assertNotEmpty($reservation->reference_code);
        $this->assertEquals(8, strlen($reservation->reference_code));
    }

    public function test_reference_codes_are_unique(): void
    {
        $codes = Reservation::factory()
            ->for(Table::factory())
            ->count(50)
            ->create()
            ->pluck('reference_code')
            ->unique();

        $this->assertCount(50, $codes);
    }

    public function test_reservation_is_cancellable_when_slot_is_far_enough_away(): void
    {
        $reservation = Reservation::factory()
            ->for(Table::factory())
            ->forDate(now()->addDays(5)->format('Y-m-d'))
            ->forSlot('18:00')
            ->make(['status' => 'confirmed']);

        $this->assertTrue($reservation->isCancellable());
    }

    public function test_reservation_is_not_cancellable_within_cutoff(): void
    {
        // Slot in 1 hour; default cutoff is 2 hours
        $slotStart = now()->addHour()->format('H:i');

        $reservation = Reservation::factory()
            ->for(Table::factory())
            ->forDate(now()->format('Y-m-d'))
            ->forSlot($slotStart)
            ->make(['status' => 'confirmed']);

        $this->assertFalse($reservation->isCancellable());
    }

    public function test_cancelled_reservation_is_not_cancellable(): void
    {
        $reservation = Reservation::factory()
            ->for(Table::factory())
            ->cancelled()
            ->make();

        $this->assertFalse($reservation->isCancellable());
    }

    public function test_cancel_method_updates_status_and_timestamps(): void
    {
        $reservation = Reservation::factory()
            ->for(Table::factory())
            ->forDate(now()->addDays(5)->format('Y-m-d'))
            ->forSlot('18:00')
            ->create(['status' => 'confirmed']);

        $reservation->cancel('Test cancellation');

        $reservation->refresh();

        $this->assertEquals('cancelled', $reservation->status);
        $this->assertNotNull($reservation->cancelled_at);
        $this->assertEquals('Test cancellation', $reservation->cancellation_reason);
    }
}
