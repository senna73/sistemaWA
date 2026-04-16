<?php

namespace Database\Seeders;

use App\Models\Collaborator;
use App\Models\ConfigTable;
use App\Models\Establishment;
use App\Models\Section;
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
        
        if (!User::findByEmail('dev@dev.com')->exists()) {
            User::factory()->create([
                'name' => 'Developer',
                'email' => 'dev@dev.com',
            ]);
        }

        $this->call([
            PermissionsSeeder::class,
        ]);
        
        Section::firstOrCreate(['name'=> 'Flv - Hortifruti',]);
        Section::firstOrCreate(['name'=> 'Flc - Frios',]);
        Section::firstOrCreate(['name'=> 'Padaria',]);
        Section::firstOrCreate(['name'=> 'Mercearia',]);
        Section::firstOrCreate(['name'=> 'Frente de Caixa',]);
        
        Section::firstOrCreate(['name'=> 'Depósito',]);
        
        Section::firstOrCreate(['name'=> 'Açougue Abastecimento',]);
        Section::firstOrCreate(['name'=> 'Açougue Cortes/Manipulação',]);
        Section::firstOrCreate(['name'=> 'FLV - Central - Bistek',]);
        
        Section::firstOrCreate(['name'=> 'Diária Proporcional',]);
        
        Section::firstOrCreate(['name'=> 'Conferência',]);
        Section::firstOrCreate(['name'=> 'Floricultura',]);
        Section::firstOrCreate(['name'=> 'Separação',]);
        
        Section::firstOrCreate(['name'=> 'Mercearia Central',]);
        Section::firstOrCreate(['name'=> 'Flv (7:20 horas)',]);
        /*
         // Criando um novo registro na tabela config_table
        ConfigTable::firstOrCreate([
            'id' => 'inss_default', // Se o id for um UUID, senão pode ser um valor fixo
            'value' => 7.5,
        ]);
        
        ConfigTable::firstOrCreate([
            'id' => 'tax_default',
            'value' => 14.32,
        ]);
        */
    }
}
