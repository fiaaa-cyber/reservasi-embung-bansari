<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Package;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        Package::create([
            'name' => 'Paket Camping A',
            'description' => 'Paket camping untuk maksimal 4 orang',
            'price' => 150000,
            'capacity' => 4,
            'is_active' => true,
        ]);

        Package::create([
            'name' => 'Paket Camping B',
            'description' => 'Paket camping untuk maksimal 6 orang',
            'price' => 200000,
            'capacity' => 6,
            'is_active' => true,
        ]);

        Package::create([
            'name' => 'Paket Camping C',
            'description' => 'Paket camping untuk maksimal 10 orang',
            'price' => 300000,
            'capacity' => 10,
            'is_active' => true,
        ]);
    }
}
