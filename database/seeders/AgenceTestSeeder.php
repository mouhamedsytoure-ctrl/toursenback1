<?php

namespace Database\Seeders;

use App\Models\Agence;
use App\Models\Immeuble;
use App\Models\Logement;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AgenceTestSeeder extends Seeder
{
    // php artisan db:seed --class=AgenceTestSeeder
    public function run(): void
    {
        $agence = Agence::create([
            'nom'             => 'Demo Immo',
            'slug'            => 'demo-immo',
            'telephone'       => '77 000 00 00',
            'ville'           => 'Thies',
            'plan'            => 'essai',
            'statut'          => 'actif',
            'quota_logements' => 10,
        ]);

        // En console personne n'est connecte : on force le tenant courant
        // avant de creer quoi que ce soit, sinon agence_id resterait vide.
        Tenant::pour($agence->id);

        $admin = User::create([
            'name'      => 'Admin Demo Immo',
            'email'     => 'admin@demo-immo.test',
            'password'  => Hash::make('password'),
            'role'      => 'super_admin',
            'is_active' => true,
            'agence_id' => $agence->id, // User n'a pas le trait BelongsToAgence, on le precise
        ]);

        $immeuble = Immeuble::create([
            'nom'        => 'Residence Demo',
            'ville'      => 'Thies',
            'created_by' => $admin->id,
        ]);

        Logement::create([
            'immeuble_id' => $immeuble->id,
            'reference'   => 'A1',
            'etage'       => 0,
            'type'        => 'studio',
            'loyer'       => 50000,
            'statut'      => 'disponible',
        ]);

        Logement::create([
            'immeuble_id' => $immeuble->id,
            'reference'   => 'A2',
            'etage'       => 1,
            'type'        => 'appartement',
            'loyer'       => 90000,
            'statut'      => 'disponible',
        ]);

        Tenant::reinitialiser();
    }
}
