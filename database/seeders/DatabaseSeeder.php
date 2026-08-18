<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Super admin : accès global, pas besoin de code organisation à la connexion.
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@maintenance.local',
            'password' => bcrypt('password'),
            'is_super_admin' => true,
        ]);

        // Organisation de démonstration + un administrateur rattaché, pour tester
        // le flux de connexion avec code organisation.
        $organisation = Organisation::create([
            'name' => 'Organisation Démo',
            'code' => 'DEMO01',
            'is_active' => true,
        ]);

        $orgAdmin = User::factory()->create([
            'name' => 'Admin Organisation',
            'email' => 'admin.org@maintenance.local',
            'password' => bcrypt('password'),
            'is_super_admin' => false,
        ]);

        $organisation->users()->attach($orgAdmin->id, [
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Utilisateur à droits restreints (parc automobile uniquement), pour vérifier
        // que le menu ne montre que les modules autorisés par le rôle.
        $parcManager = User::factory()->create([
            'name' => 'Responsable Parc',
            'email' => 'parc@maintenance.local',
            'password' => bcrypt('password'),
            'is_super_admin' => false,
        ]);

        $organisation->users()->attach($parcManager->id, [
            'role' => 'responsable_parc',
            'is_active' => true,
        ]);
    }
}
