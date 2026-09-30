<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MarketplaceSetting;
use App\Models\ServiceArea;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminMarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $settings = json_decode(file_get_contents(__DIR__.'/data/admin-settings.json'), true, 512, JSON_THROW_ON_ERROR);
            MarketplaceSetting::firstOrCreate(['id' => 1], ['values' => $settings, 'version' => 1]);
            // Import vocabulary only. Existing bookings, users and verification decisions are retained.
            $categories = DB::table('bookings')->distinct()->pluck('service_category')
                ->merge(DB::table('technician_profiles')->distinct()->pluck('trade'));
            $areas = DB::table('technician_profiles')->distinct()->pluck('service_location')
                ->merge(DB::table('bookings')->distinct()->pluck('service_area'));
            foreach ([Category::class => $categories, ServiceArea::class => $areas] as $model => $names) {
                foreach ($names->filter()->unique() as $name) {
                    $name = trim($name);
                    if ($name !== '') {
                        $model::firstOrCreate(['normalized_name' => Str::lower($name)], ['name' => $name, 'active' => true]);
                    }
                }
            }
        });
    }
}
