<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationApiTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name'    => 'Jane Doe',
            'customer_email'   => 'jane@example.com',
            'customer_phone'   => '+91-9876543210',
            'guest_count'      => 2,
            'reservation_date' => now()->addDays(2)->format('Y-m-d'),
            'slot_start'       => '12:30',
            'special_requests' => 'Window seat preferred',
        ], $overrides);
    }

    // ─── POST /api/v1/reservations ────────────────────────────────────────────

    public function test_can_book_table_with_smart_assignment(): void
    {
        Table::factory()->withCapacity(4)->indoor()->create();

        $response = $this->postJson('/api/v1/reservations', $this->validPayload());

        $response->assertCreated()
                 ->assertJsonStructure([
                     'data' => [
                         'reference_code',
                         'status',
                         'table'    => ['id', 'table_number', 'capacity', 'location'],
                         'customer' => ['name', 'email', 'phone'],
                         'slot'     => ['date', 'start', 'end'],
                         'guest_count',
                     ],
                 ])
                 ->assertJsonPath('data.status', 'confirmed')
                 ->assertJsonPath('data.customer.email', 'jane@example.com');

        $this->assertDatabaseHas('reservations', [
            'customer_email'   => 'jane@example.com',
            'status'           => 'confirmed',
        ]);
    }

    public function test_can_book_explicit_table(): void
    {
        $table = Table::factory()->withCapacity(4)->create();

        $this->postJson('/api/v1/reservations', $this->validPayload([
            'table_id' => $table->id,
        ]))->assertCreated()
           ->assertJsonPath('data.table.id', $table->id);
    }

    public function test_smart_assignment_picks_smallest_fitting_table(): void
    {
        // Two-seater and four-seater available; party of 2 should get two-seater
        $small = Table::factory()->withCapacity(2)->create(['table_number' => 1]);
        Table::factory()->withCapacity(4)->create(['table_number' => 2]);

        $response = $this->postJson('/api/v1/reservations', $this->validPayload([
            'guest_count' => 2,
        ]));

        $response->assertCreated()
                 ->assertJsonPath('data.table.id', $small->id);
    }

    public function test_cannot_book_table_beyond_capacity(): void
    {
        $table = Table::factory()->withCapacity(2)->create();

        $this->postJson('/api/v1/reservations', $this->validPayload([
            'table_id'    => $table->id,
            'guest_count' => 5,
        ]))->assertUnprocessable()
           ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'capacity'));
    }

    public function test_prevents_double_booking(): void
    {
        $table   = Table::factory()->withCapacity(4)->create();
        $payload = $this->validPayload(['table_id' => $table->id]);

        $this->postJson('/api/v1/reservations', $payload)->assertCreated();

        // Second booking for the same table/date/slot must be rejected
        $this->postJson('/api/v1/reservations', array_merge($payload, [
            'customer_email' => 'other@example.com',
        ]))->assertConflict();
    }

    public function test_returns_422_when_no_table_available(): void
    {
        // No tables seeded

        $this->postJson('/api/v1/reservations', $this->validPayload())
             ->assertUnprocessable();
    }

    public function test_validation_fails_with_missing_required_fields(): void
    {
        $this->postJson('/api/v1/reservations', [])
             ->assertUnprocessable()
             ->assertJsonValidationErrors([
                 'customer_name',
                 'customer_email',
                 'customer_phone',
                 'guest_count',
                 'reservation_date',
                 'slot_start',
             ]);
    }

    public function test_cannot_book_past_date(): void
    {
        Table::factory()->withCapacity(4)->create();

        $this->postJson('/api/v1/reservations', $this->validPayload([
            'reservation_date' => now()->subDay()->format('Y-m-d'),
        ]))->assertUnprocessable()
           ->assertJsonValidationErrors(['reservation_date']);
    }

    public function test_reference_code_is_generated_automatically(): void
    {
        Table::factory()->withCapacity(4)->create();

        $response = $this->postJson('/api/v1/reservations', $this->validPayload());

        $code = $response->json('data.reference_code');
        $this->assertNotEmpty($code);
        $this->assertEquals(8, strlen($code));
    }

    // ─── GET /api/v1/reservations/{code} ─────────────────────────────────────

    public function test_can_retrieve_reservation_by_reference_code(): void
    {
        $reservation = Reservation::factory()
            ->for(Table::factory())
            ->create(['reference_code' => 'ABCD1234']);

        $this->getJson('/api/v1/reservations/ABCD1234')
             ->assertOk()
             ->assertJsonPath('data.reference_code', 'ABCD1234')
             ->assertJsonStructure(['data' => ['table', 'customer', 'slot']]);
    }

    public function test_reference_code_lookup_is_case_insensitive(): void
    {
        Reservation::factory()
            ->for(Table::factory())
            ->create(['reference_code' => 'TESTCODE']);

        $this->getJson('/api/v1/reservations/testcode')
             ->assertOk()
             ->assertJsonPath('data.reference_code', 'TESTCODE');
    }

    public function test_returns_404_for_unknown_reference_code(): void
    {
        $this->getJson('/api/v1/reservations/XXXXXXXX')
             ->assertNotFound();
    }

    // ─── DELETE /api/v1/reservations/{code} ──────────────────────────────────

    public function test_can_cancel_a_reservation(): void
    {
        // Slot is well in the future — cancellation window is open
        $reservation = Reservation::factory()
            ->for(Table::factory())
            ->forDate(now()->addDays(5)->format('Y-m-d'))
            ->forSlot('18:30')
            ->create();

        $this->deleteJson("/api/v1/reservations/{$reservation->reference_code}", [
            'reason' => 'Change of plans',
        ])->assertOk()
          ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('reservations', [
            'id'     => $reservation->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cannot_cancel_within_cutoff_window(): void
    {
        // Slot starts in 1 hour — within the 2-hour cancellation cutoff
        $slotStart = now()->addHour()->format('H:i');

        $reservation = Reservation::factory()
            ->for(Table::factory())
            ->forDate(now()->format('Y-m-d'))
            ->forSlot($slotStart)
            ->create();

        $this->deleteJson("/api/v1/reservations/{$reservation->reference_code}")
             ->assertUnprocessable()
             ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'cannot be cancelled'));
    }

    public function test_cannot_cancel_an_already_cancelled_reservation(): void
    {
        $reservation = Reservation::factory()
            ->for(Table::factory())
            ->cancelled()
            ->create();

        $this->deleteJson("/api/v1/reservations/{$reservation->reference_code}")
             ->assertUnprocessable();
    }

    public function test_cancellation_reason_is_stored(): void
    {
        $reservation = Reservation::factory()
            ->for(Table::factory())
            ->forDate(now()->addDays(5)->format('Y-m-d'))
            ->forSlot('19:00')
            ->create();

        $this->deleteJson("/api/v1/reservations/{$reservation->reference_code}", [
            'reason' => 'Feeling unwell',
        ])->assertOk();

        $this->assertDatabaseHas('reservations', [
            'id'                  => $reservation->id,
            'cancellation_reason' => 'Feeling unwell',
        ]);
    }
}
