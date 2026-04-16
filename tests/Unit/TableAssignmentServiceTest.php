<?php

namespace Tests\Unit;

use App\Models\Reservation;
use App\Models\Table;
use App\Services\TableAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private TableAssignmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TableAssignmentService();
    }

    private function date(): string
    {
        return now()->addDays(3)->format('Y-m-d');
    }

    public function test_picks_smallest_table_that_fits_the_party(): void
    {
        $small  = Table::factory()->withCapacity(2)->create(['table_number' => 1]);
        $medium = Table::factory()->withCapacity(4)->create(['table_number' => 2]);
        $large  = Table::factory()->withCapacity(8)->create(['table_number' => 3]);

        $table = $this->service->findBestTable(3, $this->date(), '12:30');

        // Party of 3 — two-seater cannot fit, should get medium (4-seater)
        $this->assertEquals($medium->id, $table->id);
    }

    public function test_returns_null_when_no_table_can_fit_party(): void
    {
        Table::factory()->withCapacity(2)->create();

        $table = $this->service->findBestTable(10, $this->date(), '12:30');

        $this->assertNull($table);
    }

    public function test_excludes_already_booked_tables(): void
    {
        $bookedTable = Table::factory()->withCapacity(4)->create(['table_number' => 1]);
        $freeTable   = Table::factory()->withCapacity(4)->create(['table_number' => 2]);

        Reservation::factory()
            ->forDate($this->date())
            ->forSlot('12:30')
            ->create([
                'table_id'    => $bookedTable->id,
                'guest_count' => 2,
                'status'      => 'confirmed',
            ]);

        $table = $this->service->findBestTable(2, $this->date(), '12:30');

        $this->assertEquals($freeTable->id, $table->id);
    }

    public function test_prefers_requested_location_when_available(): void
    {
        $indoor  = Table::factory()->withCapacity(4)->indoor()->create(['table_number' => 1]);
        $outdoor = Table::factory()->withCapacity(4)->outdoor()->create(['table_number' => 2]);

        $table = $this->service->findBestTable(2, $this->date(), '12:30', 'outdoor');

        $this->assertEquals($outdoor->id, $table->id);
    }

    public function test_falls_back_to_any_location_if_preferred_unavailable(): void
    {
        $indoor = Table::factory()->withCapacity(4)->indoor()->create(['table_number' => 1]);

        // Only indoor available — request outdoor
        $table = $this->service->findBestTable(2, $this->date(), '12:30', 'outdoor');

        $this->assertEquals($indoor->id, $table->id);
    }

    public function test_tiebreaker_is_lowest_table_number(): void
    {
        // Two identical 4-seater tables; should pick the lower-numbered one
        $first  = Table::factory()->withCapacity(4)->create(['table_number' => 3]);
        $second = Table::factory()->withCapacity(4)->create(['table_number' => 7]);

        $table = $this->service->findBestTable(3, $this->date(), '14:00');

        $this->assertEquals($first->id, $table->id);
    }

    public function test_cancelled_booking_does_not_block_table(): void
    {
        $table = Table::factory()->withCapacity(4)->create();

        Reservation::factory()
            ->forDate($this->date())
            ->forSlot('12:30')
            ->cancelled()
            ->create(['table_id' => $table->id, 'guest_count' => 2]);

        $assigned = $this->service->findBestTable(2, $this->date(), '12:30');

        $this->assertEquals($table->id, $assigned->id);
    }

    public function test_get_available_tables_returns_unbooked_tables(): void
    {
        $free   = Table::factory()->withCapacity(4)->create(['table_number' => 1]);
        $booked = Table::factory()->withCapacity(4)->create(['table_number' => 2]);

        Reservation::factory()
            ->forDate($this->date())
            ->forSlot('12:30')
            ->create(['table_id' => $booked->id, 'guest_count' => 2]);

        $available = $this->service->getAvailableTables($this->date(), '12:30');

        $ids = $available->pluck('id')->all();
        $this->assertContains($free->id, $ids);
        $this->assertNotContains($booked->id, $ids);
    }
}
