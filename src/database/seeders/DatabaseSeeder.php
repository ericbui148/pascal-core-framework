<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);
    }
}

// ─────────────────────────────────────────────────────────────────────────────

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'admin@pascal.com';
        $password = 'Admin@123456';

        $exists = DB::table('pascal_users')->where('email', $email)->exists();

        if (!$exists) {
            DB::table('pascal_users')->insert([
                'name'              => 'admin',
                'docstatus'         => 0,
                'full_name'         => 'System Administrator',
                'email'             => $email,
                'password'          => Hash::make($password),
                'role'              => 'admin',
                'status'            => 'Active',
                'email_verified_at' => now(),
                'owner'             => 'system',
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }

        $this->command->newLine();
        $this->command->info('  ✓  Admin user seeded');
        $this->command->line("     Email   : {$email}");
        $this->command->line("     Password: {$password}");
        $this->command->warn('     Change password immediately after first login!');
        $this->command->newLine();
    }
}
