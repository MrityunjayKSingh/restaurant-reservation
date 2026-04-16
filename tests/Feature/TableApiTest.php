<?php

namespace Tests\Feature;

use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableApiTest extends TestCase
{
    use RefreshDatabase;

    // ─── POST /api/v1/tables ──────────────────────────────────────────────────

    public function test_can_create_a_table(): void
    {
        $payload = [
            'table_number' => 1,
            'capacity'     => 4,
            'location'     => 'indoor',
        ];

        $response = $this->postJson('/api/v1/tables', $payload);

        $response->assertCreated()
                 ->assertJsonPath('data.table_number', 1)
                 ->assertJsonPath('data.capacity', 4)
                 ->assertJsonPath('data.location', 'indoor');

        $this->assertDatabaseHas('tables', ['table_number' => 1, 'capacity' => 4]);
    }

    public function test_create_table_requires_all_fields(): void
    {
        $this->postJson('/api/v1/tables', [])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['table_number', 'capacity', 'location']);
    }

    public function test_table_number_must_be_unique(): void
    {
        Table::factory()->create(['table_number' => 5]);

        $this->postJson('/api/v1/tables', [
            'table_number' => 5,
            'capacity'     => 4,
            'location'     => 'indoor',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['table_number']);
    }

    public function test_location_must_be_indoor_or_outdoor(): void
    {
        $this->postJson('/api/v1/tables', [
            'table_number' => 99,
            'capacity'     => 4,
            'location'     => 'rooftop',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['location']);
    }

    public function test_capacity_must_be_positive_integer(): void
    {
        $this->postJson('/api/v1/tables', [
            'table_number' => 99,
            'capacity'     => 0,
            'location'     => 'indoor',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['capacity']);
    }

    // ─── GET /api/v1/tables ───────────────────────────────────────────────────

    public function test_can_list_all_active_tables(): void
    {
        Table::factory()->count(3)->create();
        Table::factory()->inactive()->create();

        $response = $this->getJson('/api/v1/tables');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_filter_tables_by_location(): void
    {
        Table::factory()->indoor()->count(2)->create();
        Table::factory()->outdoor()->count(3)->create();

        $this->getJson('/api/v1/tables?location=indoor')
             ->assertOk()
             ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/tables?location=outdoor')
             ->assertOk()
             ->assertJsonCount(3, 'data');
    }

    // ─── GET /api/v1/tables/{id} ──────────────────────────────────────────────

    public function test_can_show_a_single_table(): void
    {
        $table = Table::factory()->create(['table_number' => 7]);

        $this->getJson("/api/v1/tables/{$table->id}")
             ->assertOk()
             ->assertJsonPath('data.table_number', 7);
    }

    public function test_returns_404_for_nonexistent_table(): void
    {
        $this->getJson('/api/v1/tables/9999')
             ->assertNotFound();
    }
}
