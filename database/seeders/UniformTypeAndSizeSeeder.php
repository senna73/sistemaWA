<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UniformTypeAndSizeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Kit Padrão', 'active' => true],
            ['name' => 'Kit Líder', 'active' => true],
            ['name' => 'Kit Açougue', 'active' => true],
            ['name' => 'Kit Padaria', 'active' => true],
        ];

        foreach ($types as $type) {
            DB::table('uniform_types')->updateOrInsert(
                ['name' => $type['name']],
                [
                    'active' => $type['active'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $sizes = [
            ['name' => 'PP'],
            ['name' => 'P'],
            ['name' => 'M'],
            ['name' => 'G'],
            ['name' => 'GG'],
            ['name' => 'XG'],
            ['name' => 'EXG'],
        ];

        foreach ($sizes as $size) {
            DB::table('uniform_sizes')->updateOrInsert(
                ['name' => $size['name']],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}