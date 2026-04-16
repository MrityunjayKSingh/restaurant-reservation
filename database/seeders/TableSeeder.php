<?php

namespace Database\Seeders;

use App\Models\Table;
use Illuminate\Database\Seeder;

class TableSeeder extends Seeder
{
    /**
     * Seed a realistic restaurant layout:
     *   Indoor:  4× 2-seater, 4× 4-seater, 2× 6-seater, 1× 8-seater
     *   Outdoor: 3× 2-seater, 3× 4-seater, 1× 6-seater
     */
    public function run(): void
    {
        $tables = [
            // Indoor tables
            ['table_number' => 1,  'capacity' => 2, 'location' => 'indoor'],
            ['table_number' => 2,  'capacity' => 2, 'location' => 'indoor'],
            ['table_number' => 3,  'capacity' => 2, 'location' => 'indoor'],
            ['table_number' => 4,  'capacity' => 2, 'location' => 'indoor'],
            ['table_number' => 5,  'capacity' => 4, 'location' => 'indoor'],
            ['table_number' => 6,  'capacity' => 4, 'location' => 'indoor'],
            ['table_number' => 7,  'capacity' => 4, 'location' => 'indoor'],
            ['table_number' => 8,  'capacity' => 4, 'location' => 'indoor'],
            ['table_number' => 9,  'capacity' => 6, 'location' => 'indoor'],
            ['table_number' => 10, 'capacity' => 6, 'location' => 'indoor'],
            ['table_number' => 11, 'capacity' => 8, 'location' => 'indoor', 'notes' => 'Private dining area'],

            // Outdoor tables
            ['table_number' => 12, 'capacity' => 2, 'location' => 'outdoor'],
            ['table_number' => 13, 'capacity' => 2, 'location' => 'outdoor'],
            ['table_number' => 14, 'capacity' => 2, 'location' => 'outdoor'],
            ['table_number' => 15, 'capacity' => 4, 'location' => 'outdoor'],
            ['table_number' => 16, 'capacity' => 4, 'location' => 'outdoor'],
            ['table_number' => 17, 'capacity' => 4, 'location' => 'outdoor'],
            ['table_number' => 18, 'capacity' => 6, 'location' => 'outdoor', 'notes' => 'Garden view'],
        ];

        foreach ($tables as $table) {
            Table::create(array_merge($table, ['is_active' => true]));
        }

        $this->command->info('Seeded ' . count($tables) . ' tables.');
    }
}
