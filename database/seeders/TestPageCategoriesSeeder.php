<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestPageCategoriesSeeder extends Seeder
{
    /**
     * Seed test page categories for entity 1 (University).
     */
    public function run(): void
    {
        $entityId = 1; // Change this to match your test entity

        // Categories
        $categories = [
            [
                'entity_id' => $entityId,
                'category_name' => 'About',
                'category_slug' => 'about',
                'is_menu' => true,
                'menu_text' => 'About Us',
                'menu_order' => 1,
                'link_url' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'entity_id' => $entityId,
                'category_name' => 'Academics',
                'category_slug' => 'academics',
                'is_menu' => true,
                'menu_text' => 'Academics',
                'menu_order' => 2,
                'link_url' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'entity_id' => $entityId,
                'category_name' => 'Admissions',
                'category_slug' => 'admissions',
                'is_menu' => true,
                'menu_text' => 'Admissions',
                'menu_order' => 3,
                'link_url' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($categories as $category) {
            $catId = DB::table('entity_page_categories')->insertGetId($category);

            // Add subcategories for "About"
            if ($category['category_name'] === 'About') {
                $subcategories = [
                    [
                        'category_id' => $catId,
                        'subcategory_name' => 'Mission & Vision',
                        'subcategory_slug' => 'mission-vision',
                        'is_menu' => true,
                        'menu_text' => 'Mission & Vision',
                        'menu_order' => 1,
                        'link_url' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'category_id' => $catId,
                        'subcategory_name' => 'History',
                        'subcategory_slug' => 'history',
                        'is_menu' => true,
                        'menu_text' => 'Our History',
                        'menu_order' => 2,
                        'link_url' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ];

                foreach ($subcategories as $subcategory) {
                    DB::table('entity_page_subcategories')->insert($subcategory);
                }
            }
        }

        $this->command->info('Test page categories seeded successfully for entity ' . $entityId);
    }
}
