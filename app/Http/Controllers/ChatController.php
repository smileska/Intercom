<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\User;
use Illuminate\View\View;

class ChatController extends Controller
{

    public function index(?Channel $channel = null, ?User $user = null): View
    {
        return view('chat', [
            'initialChannel' => $channel,
            'initialUser' => $user,
        ]);
    }
}
