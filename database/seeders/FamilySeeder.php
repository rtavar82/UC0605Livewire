<?php

namespace Database\Seeders;

use App\Models\Family;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FamilySeeder extends Seeder
{
    /**
     * Seed the botanical families.
     */
    public function run(): void
    {
        $families = [
            'Araceae' => 'Família das aráceas, que inclui a Monstera, o Philodendron e o Anthurium.',
            'Cactaceae' => 'Plantas suculentas adaptadas a climas áridos, geralmente com espinhos.',
            'Rosaceae' => 'Inclui a roseira, a macieira, a pereira e o morangueiro.',
            'Asparagaceae' => 'Inclui os espargos e plantas ornamentais como a Dracaena e a Sansevieria.',
            'Crassulaceae' => 'Plantas suculentas com folhas carnudas, como a Crassula e a Echeveria.',
            'Lamiaceae' => 'Inclui plantas aromáticas como a hortelã, o alecrim e o manjericão.',
            'Marantaceae' => 'Plantas tropicais de folhagem ornamental, como a Maranta e a Calathea.',
            'Moraceae' => 'Inclui a figueira, a amoreira e várias espécies de Ficus.',
            'Orchidaceae' => 'Família das orquídeas, conhecida pela diversidade das suas flores.',
            'Solanaceae' => 'Inclui o tomateiro, a batateira, o pimenteiro e a petúnia.',
        ];

        foreach ($families as $name => $description) {
            Family::withTrashed()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $description],
            );
        }
    }
}
