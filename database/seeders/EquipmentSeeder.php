<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Equipment;

class EquipmentSeeder extends Seeder
{
    public function run(): void
    {
        Equipment::create([
            'name' => 'Kompor',
            'description' => 'Perlengkapan kompor untuk kebutuhan camping',
            'price' => 25000,
            'stock' => 10,
            'is_active' => true,
        ]);

        Equipment::create([
            'name' => 'Tikar',
            'description' => 'Tikar untuk area camping',
            'price' => 15000,
            'stock' => 20,
            'is_active' => true,
        ]);

        Equipment::create([
            'name' => 'Lampu Camping',
            'description' => 'Lampu untuk penerangan area camping',
            'price' => 20000,
            'stock' => 10,
            'is_active' => true,
        ]);

        Equipment::create([
            'name' => 'Sleeping Bag',
            'description' => 'Sleeping bag untuk kebutuhan camping',
            'price' => 30000,
            'stock' => 10,
            'is_active' => true,
        ]);
    }
}
