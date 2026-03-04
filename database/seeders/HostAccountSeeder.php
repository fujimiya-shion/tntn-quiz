<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class HostAccountSeeder extends Seeder
{
    public function run(): void
    {
        $hostEmail = (string) config('quiz.host_account.email');
        $hostName = (string) config('quiz.host_account.name');

        $password = config('app.env') === 'production'
            ? (string) config('quiz.host_account.password')
            : '123456';

        if (config('app.env') === 'production' && $password === '') {
            throw new InvalidArgumentException('QUIZ_HOST_PASSWORD is required in production environment.');
        }

        User::query()->updateOrCreate(
            ['email' => $hostEmail],
            [
                'name' => $hostName,
                'password' => Hash::make($password),
            ]
        );
    }
}
