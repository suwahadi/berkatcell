<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class AssignUserRole extends Command
{
    protected $signature = 'user:role {email : Email akun} {role=admin : admin atau customer}';

    protected $description = 'Tetapkan peran (admin|customer) untuk user berdasarkan email.';

    public function handle(): int
    {
        $role = UserRole::tryFrom((string) $this->argument('role'));

        if ($role === null) {
            $this->error('Peran tidak valid. Gunakan: admin atau customer.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error("User dengan email {$this->argument('email')} tidak ditemukan.");

            return self::FAILURE;
        }

        $user->update(['role' => $role]);

        $this->info("Peran {$user->email} kini: {$role->value} ({$role->label()}).");

        return self::SUCCESS;
    }
}
