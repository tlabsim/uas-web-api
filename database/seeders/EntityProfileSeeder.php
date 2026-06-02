<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EntityProfileSeeder extends Seeder
{
    /**
     * Seed entity profiles from IMS API
     */
    public function run(): void
    {
        $imsApiUrl = config('ims.api_base_url');
        $imsApiKey = config('ims.api_key');

        if (empty($imsApiUrl)) {
            $this->command->error('IMS API URL is not configured. Please set IMS_API_BASE_URL in .env');
            return;
        }

        $this->command->info('Fetching entities from IMS API...');

        try {
            // Fetch entities from IMS API
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'X-API-Key' => $imsApiKey,
            ])->timeout(30)->get("{$imsApiUrl}/entities");

            if (!$response->successful()) {
                $this->command->error('Failed to fetch entities from IMS API: ' . $response->status());
                $this->command->error('Response: ' . $response->body());
                return;
            }

            $entities = $response->json('data', []);

            if (empty($entities)) {
                $this->command->warn('No entities found in IMS API response');
                return;
            }

            $this->command->info('Found ' . count($entities) . ' entities');

            $now = now();
            $insertedCount = 0;
            $skippedCount = 0;

            foreach ($entities as $entity) {
                // Check if entity already exists
                $exists = DB::table('entity_profiles')
                    ->where('id', $entity['id'])
                    ->exists();

                if ($exists) {
                    $this->command->line("Skipping existing entity: {$entity['entity_name']} (ID: {$entity['id']})");
                    $skippedCount++;
                    continue;
                }

                // Insert entity profile
                DB::table('entity_profiles')->insert([
                    'id' => $entity['id'],
                    'entity_name' => $entity['entity_name'] ?? null,
                    'entity_name_bn' => $entity['entity_name_bn'] ?? null,
                    'entity_short_name' => $entity['entity_short_name'] ?? null,
                    'entity_type' => $entity['entity_type'] ?? null,
                    'parent_entity_id' => $entity['parent_entity_id'] ?? null,
                    'entity_head_user_id' => $entity['entity_head_user_id'] ?? null,
                    'entity_head_designation' => $entity['entity_head_designation'] ?? null,
                    'about' => $entity['about'] ?? null,
                    'vision' => $entity['vision'] ?? null,
                    'mission' => $entity['mission'] ?? null,
                    'contact_email' => $entity['contact_email'] ?? null,
                    'contact_phone' => $entity['contact_phone'] ?? null,
                    'contact_address' => $entity['contact_address'] ?? null,
                    'website_url' => $entity['website_url'] ?? null,
                    'logo_url' => $entity['logo_url'] ?? null,
                    'sort_order' => $entity['sort_order'] ?? 0,
                    'is_active' => $entity['is_active'] ?? true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $this->command->info("✓ Inserted: {$entity['entity_name']} (ID: {$entity['id']})");
                $insertedCount++;

                // Also insert into entities_cache
                DB::table('entities_cache')->insert([
                    'entity_id' => $entity['id'],
                    'entity_name' => $entity['entity_name'] ?? null,
                    'entity_type' => $entity['entity_type'] ?? null,
                    'parent_entity_id' => $entity['parent_entity_id'] ?? null,
                    'raw_data' => json_encode($entity),
                    'cached_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->command->newLine();
            $this->command->info("✅ Successfully inserted {$insertedCount} entities");
            if ($skippedCount > 0) {
                $this->command->warn("⚠️  Skipped {$skippedCount} existing entities");
            }

        } catch (\Exception $e) {
            $this->command->error('Error fetching entities from IMS API: ' . $e->getMessage());
            Log::error('Entity seeding failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
