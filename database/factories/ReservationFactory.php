<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        $slotStart = $this->faker->randomElement(['11:00', '12:30', '14:00', '15:30', '17:00', '18:30', '20:00']);
        $slotEnd   = \Carbon\Carbon::parse($slotStart)->addMinutes(90)->format('H:i');

        return [
            'table_id'         => Table::factory(),
            'customer_name'    => $this->faker->name(),
            'customer_email'   => $this->faker->unique()->safeEmail(),
            'customer_phone'   => $this->faker->phoneNumber(),
            'guest_count'      => $this->faker->numberBetween(1, 4),
            'special_requests' => $this->faker->optional(0.3)->sentence(),
            'reservation_date' => $this->faker->dateTimeBetween('now', '+14 days')->format('Y-m-d'),
            'slot_start'       => $slotStart,
            'slot_end'         => $slotEnd,
            'status'           => 'confirmed',
        ];
    }

    public function cancelled(): static
    {
        return $this->state([
            'status'              => 'cancelled',
            'cancelled_at'        => now(),
            'cancellation_reason' => $this->faker->sentence(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(['status' => 'completed']);
    }

    public function forDate(string $date): static
    {
        return $this->state(['reservation_date' => $date]);
    }

    public function forSlot(string $slotStart): static
    {
        $slotEnd = \Carbon\Carbon::parse($slotStart)->addMinutes(90)->format('H:i');

        return $this->state([
            'slot_start' => $slotStart,
            'slot_end'   => $slotEnd,
        ]);
    }
}
