<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    /**
     * As 11 províncias de Moçambique. Pode correr várias vezes sem duplicar.
     */
    public const PROVINCES = [
        'Cabo Delgado',
        'Gaza',
        'Inhambane',
        'Manica',
        'Maputo Cidade',
        'Maputo Província',
        'Nampula',
        'Niassa',
        'Sofala',
        'Tete',
        'Zambézia',
    ];

    public function run(): void
    {
        foreach (self::PROVINCES as $name) {
            Province::firstOrCreate(['code' => Province::codeFromName($name)], ['name' => $name]);
        }
    }
}
