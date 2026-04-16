<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_slots_for_a_valid_date(): void
    {
        Table::factory()->count(3)->create();
        $date = now()->addDay()->format('Y-m-d');

        $response = $this->getJson("/api/v1/availability?date={$date}");

        $response->assertOk()
                 ->assertJsonStructure([
                     'data' => [
                         '*' => ['slot_start', 'slot_end', 'label', 'available_tables', 'available_count'],
                     ],
                     'meta' => ['date', 'guest_count', 'total_slots'],
                 ]);

        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_slot_available_count_decreases_after_booking(): void
    {
        $table = Table::factory()->withCapacity(4)->create();
        $date  = now()->addDay()->format('Y-m-d');

        // Grab the first slot
        $slotsRes  = $this->getJson("/api/v1/availability?date={$date}");
        $firstSlot = $slotsRes->json('data.0.slot_start');
        $countBefore = $slotsRes->json('data.0.available_count');

        // Book that slot
        Reservation::factory()
            ->forDate($date)
            ->forSlot($firstSlot)
            ->create(['table_id' => $table->id, 'guest_count' => 2]);

        $slotsAfter  = $this->getJson("/api/v1/availability?date={$date}");
        $countAfter  = $slotsAfter->json('data.0.available_count');

        $this->assertEquals($countBefore - 1, $countAfter);
    }

    public function test_filters_tables_by_guest_count(): void
    {
        Table::factory()->withCapacity(2)->count(2)->create();
        Table::factory()->withCapacity(6)->count(2)->create();

        $date = now()->addDay()->format('Y-m-d');

        // Asking for 5 guests should only show 6-seater tables
        $response = $this->getJson("/api/v1/availability?date={$date}&guest_count=5");
        $firstSlot = $response->json('data.0');

        foreach ($firstSlot['available_tables'] as $t) {
            $this->assertGreaterThanOrEqual(5, $t['capacity']);
        }
    }

    public function test_date_is_required(): void
    {
        $this->getJson('/api/v1/availability')
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['date']);
    }

    public function test_rejects_past_date(): void
    {
        $past = now()->subDay()->format('Y-m-d');

        $this->getJson("/api/v1/availability?date={$past}")
             ->assertStatus(422);
    }
}
