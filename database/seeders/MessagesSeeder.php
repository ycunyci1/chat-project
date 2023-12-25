<?php

namespace Database\Seeders;

use App\Models\User;
use Dd1\Chat\Models\Chat;
use Dd1\Chat\Models\Message;
use Illuminate\Database\Seeder;

class MessagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 0; $i < 40; $i++) {
            Chat::create();
        }
        $chats = Chat::all();
        foreach ($chats as $chat) {
            $chat->users()->attach(User::query()->inRandomOrder()->take(2)->get()->pluck('id')->toArray());
        }

        for ($i = 0; $i < 400; $i++) {
            $chat = Chat::query()->inRandomOrder()->first();
            Message::query()->create([
                'text' => fake()->text(20),
                'chat_id' => $chat->id,
                'user_id' => fake()->randomElement($chat->users)->id,
            ]);
        }
    }
}
