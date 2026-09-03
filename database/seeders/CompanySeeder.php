<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\ChannelMemberSuggestion;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompanySeeder extends Seeder
{

    public function run(): void
    {
        $admin = User::create([
            'name' => 'admin',
            'email' => 'admin@company.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'title' => 'IT Manager',
            'avatar_color' => '#4A154B',
            'bio' => 'Одговорна за IT инфраструктурата и овој интерком. Пиши слободно.',
            'email_verified_at' => now(),
        ]);

        $employees = collect([
            ['name' => 'korisnik1', 'email' => 'korisnik1@company.test', 'title' => 'Backend Developer', 'color' => '#1264A3', 'bio' => 'Laravel / PHP. Кафе задолжително пред 10ч.'],
            ['name' => 'korisnik2', 'email' => 'korisnik2@company.test', 'title' => 'Frontend Developer', 'color' => '#007A5A', 'bio' => 'Vue, Tailwind, дизајн системи.'],
            ['name' => 'korisnik3', 'email' => 'korisnik3@company.test', 'title' => 'Project Manager', 'color' => '#E01E5A', 'bio' => 'Ако имаш прашање за рокови — јас сум тука.'],
            ['name' => 'korisnik4', 'email' => 'korisnik4@company.test', 'title' => 'HR Specialist', 'color' => '#ECB22E', 'bio' => 'Луѓе, договори, боледувања, тимбилдинзи.'],
            ['name' => 'korisnik5', 'email' => 'korisnik5@company.test', 'title' => 'QA Engineer', 'color' => '#1264A3', 'bio' => 'Ако падне — јас го скршив, но со причина.'],
        ])->map(function ($data) {
            return User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make('password'),
                'role' => 'employee',
                'title' => $data['title'],
                'avatar_color' => $data['color'],
                'bio' => $data['bio'],
                'email_verified_at' => now(),
            ]);
        });

        $allUsers = $employees->push($admin);

        $channelsData = [
            ['name' => 'general', 'description' => 'Општи објави за целата компанија', 'private' => false],
            ['name' => 'random', 'description' => 'Неформални разговори', 'private' => false],
            ['name' => 'razvoj', 'description' => 'Развоен тим - технички теми', 'private' => false],
            ['name' => 'menadzment', 'description' => 'Приватен канал за менаџментот', 'private' => true],
        ];

        $channels = collect();

        foreach ($channelsData as $data) {
            $channel = Channel::create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'description' => $data['description'],
                'is_private' => $data['private'],
                'created_by' => $admin->id,
            ]);

            if ($data['private']) {
                $channel->users()->attach($admin->id, ['role' => 'owner']);
            } else {
                $channel->users()->attach($allUsers->pluck('id')->toArray(), ['role' => 'member']);
            }

            $channels->push($channel);
        }

        $general = $channels->firstWhere('name', 'general');
        $random = $channels->firstWhere('name', 'random');
        $razvoj = $channels->firstWhere('name', 'razvoj');

        $sample = [
            [$general, $admin, 'Добредојдовте на новиот интерен систем за комуникација! 🎉'],
            [$general, $employees[0], 'Супер, конечно нешто побрзо од е-пошта.'],
            [$general, $employees[1], 'Се согласувам, ова изгледа одлично.'],
            [$razvoj, $employees[0], 'Push-нав нова верзија на API-то, ве молам тестирајте.'],
            [$razvoj, $employees[4], 'Ќе проверам денес попладне.'],
            [$random, $employees[3], 'Некој за кафе во 15ч? ☕'],
            [$random, $employees[2], 'Јас сум за!'],
        ];

        foreach ($sample as [$channel, $user, $body]) {
            Message::create([
                'channel_id' => $channel->id,
                'sender_id' => $user->id,
                'body' => $body,
            ]);
        }

        Message::create([
            'sender_id' => $admin->id,
            'receiver_id' => $employees[0]->id,
            'body' => 'korisnik1, имаш ли слободни 10 минути за краток разговор?',
        ]);

        Message::create([
            'sender_id' => $employees[0]->id,
            'receiver_id' => $admin->id,
            'body' => 'Секако, слободен сум веднаш по ручек.',
        ]);

        $razvoj->users()->detach($employees[4]->id);

        ChannelMemberSuggestion::create([
            'channel_id' => $razvoj->id,
            'user_id' => $employees[4]->id,
            'suggested_by' => $employees[0]->id,
            'status' => 'pending',
        ]);
    }
}
