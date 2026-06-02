<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {      
        // Seed entity profiles from IMS API
        $this->call([
            EntityProfileSeeder::class,
        ]);
        
        // Seed post categories with default categories and metadata schemas
        $this->call([
            PostCategorySeeder::class,
        ]);
    }
}
