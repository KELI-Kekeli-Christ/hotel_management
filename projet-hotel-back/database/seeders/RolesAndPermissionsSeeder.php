<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Réinitialiser le cache des rôles et permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::query()->delete();
        Role::query()->delete();

        // --- Définition des Permissions ---
        $permissions = [
            // Utilisateurs
            'user-list', 'user-create', 'user-view', 'user-update', 'user-delete', 'user-update-role',
            'user-view-self', 'user-update-self',
            // Chambres
            'room-list', 'room-create', 'room-view', 'room-update', 'room-delete',
            // Réservations
            'reservation-list-all', 'reservation-create-for-others', 'reservation-view-any', 'reservation-update-any', 'reservation-delete-any',
            'reservation-list-own', 'reservation-create-own',
            // Services (Bar, Resto, Spa)
            'service-menu-manage', 'service-order-manage', 'service-stats-view',
            // Facturation
            'invoice-generate', 'invoice-list', 'invoice-view', 'invoice-update-status',
            // Système
            'system-role-manage', 'system-dashboard-view'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);

        }
        $this->command->info('Permissions created.');

        // --- Définition des Rôles et Assignation des Permissions ---

        // Rôle Client (permissions de base)
        $clientRole = Role::firstOrCreate(['name' => 'Client', 'guard_name' => 'api']);
        $clientRole->syncPermissions([
            'user-view-self', 'user-update-self',
            'reservation-list-own', 'reservation-create-own'
        ]);
        $this->command->info('Client role and permissions assigned.');

        // Rôle Service Manager
        $serviceManagerRole = Role::firstOrCreate(['name' => 'Service-Manager', 'guard_name' => 'api']);
        $serviceManagerRole->syncPermissions([
            'system-dashboard-view',
            'service-menu-manage', 'service-order-manage', 'service-stats-view'
        ]);
        $this->command->info('Service-Manager role and permissions assigned.');

        // Rôle Réceptionniste
        $receptionistRole = Role::firstOrCreate(['name' => 'Receptionist', 'guard_name' => 'api']);
        $receptionistRole->syncPermissions([
            'system-dashboard-view',
            'user-list', 'user-create', 'user-view', 'user-update',
            'room-list', 'room-create', 'room-view', 'room-update', 'room-delete',
            'reservation-list-all', 'reservation-create-for-others', 'reservation-view-any', 'reservation-update-any', 'reservation-delete-any',
            'invoice-generate', 'invoice-list', 'invoice-view', 'invoice-update-status'
        ]);
        $this->command->info('Receptionist role and permissions assigned.');

        // Rôle Économiste (peut seulement voir les factures)
        $economisteRole = Role::firstOrCreate(['name' => 'Économiste', 'guard_name' => 'api']);
        $economisteRole->syncPermissions([
            'system-dashboard-view',
            'invoice-list', 'invoice-view'
        ]);
        $this->command->info('Économiste role and permissions assigned.');

        // Rôle Super Admin (a toutes les permissions)
        $superAdminRole = Role::firstOrCreate(['name' => 'Super-Admin', 'guard_name' => 'api']);
        $superAdminRole->syncPermissions(Permission::all());
        $this->command->info('Super-Admin role created and given all permissions.');

        // --- Création de l'utilisateur Super Admin de test ---
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@hotel.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin') // A CHANGER
            ]
        );
        $adminUser->assignRole($superAdminRole);
        $this->command->info('Super Admin user created and assigned.');
    }
}
