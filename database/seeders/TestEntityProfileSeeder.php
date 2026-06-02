<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestEntityProfileSeeder extends Seeder
{
    /**
     * Seed test entity profiles for development.
     * This assumes you have entity IDs from IMS Core.
     */
    public function run(): void
    {
        // Example: Entity ID 1 = University
        // Entity ID 5 = Faculty of Science
        // Entity ID 10 = Department of CSE
        
        $entities = [
            [
                'entity_id' => 1,
                'head_designation' => null,
                'establishment_date' => '2006-01-01',
                'slug' => 'nstu',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'entity_id' => 5,
                'head_designation' => null,
                'establishment_date' => '2006-01-01',
                'slug' => 'faculty-of-science',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'entity_id' => 10,
                'head_designation' => null,
                'establishment_date' => '2010-01-01',
                'slug' => 'cse',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($entities as $entity) {
            DB::table('entity_profiles')->updateOrInsert(
                ['entity_id' => $entity['entity_id']],
                $entity
            );
        }

        $this->command->info('Test entity profiles seeded successfully!');
        $this->command->info('Note: Make sure these entity IDs exist in IMS Core and you have web_curator role for them.');
    }
}
