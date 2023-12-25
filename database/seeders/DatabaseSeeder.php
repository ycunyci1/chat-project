<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()
            ->create([
                'name' => 'Павел Сергеевич',
                'email' => 'ycunyci@mail.ru',
                'password' => Hash::make('123qweasd'),
                'avatar' => 'http://new-project-chat.test/storage/files/pavel.jpg',
                'email_verified_at' => now(),
            ]);
        User::factory(69)->create();
        $this->call(MessagesSeeder::class);
    }
}
