<?php

namespace App\Livewire;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Livewire\WithFileUploads;

class Chat extends Component
{
    use WithFileUploads;

    public string $activeType = 'channel';

    public ?int $activeChannelId = null;

    public ?int $activeUserId = null;

    public string $newMessage = '';

    public bool $showCreateChannel = false;

    public string $newChannelName = '';

    public string $newChannelDescription = '';

    public bool $newChannelPrivate = false;

    public array $newChannelMembers = [];

    public ?int $editingMessageId = null;

    public string $editingBody = '';

    public string $userSearch = '';

    public bool $showProfile = false;

    public ?int $profileUserId = null;

    public bool $showEditProfile = false;

    public string $pName = '';

    public string $pEmail = '';

    public string $pTitle = '';

    public string $pBio = '';

    public $pAvatar = null;

    public string $pCurrentPassword = '';

    public string $pPassword = '';

    public string $pPasswordConfirmation = '';

    public string $flash = '';

    public int $notifiedThroughId = 0;

    public function mount(?Channel $initialChannel = null, ?User $initialUser = null): void
    {
        $this->notifiedThroughId = (int) (Message::whereNotNull('receiver_id')
            ->where('receiver_id', Auth::id())
            ->max('id') ?? 0);

        $this->touchPresence();

        if ($initialUser && $initialUser->exists) {
            $this->activeType = 'dm';
            $this->activeUserId = $initialUser->id;
            $this->markThreadRead($initialUser->id);

            return;
        }

        if ($initialChannel && $initialChannel->exists && $this->canAccessChannel($initialChannel)) {
            $this->activeType = 'channel';
            $this->activeChannelId = $initialChannel->id;

            return;
        }

        $first = $this->visibleChannels()->first();
        $this->activeChannelId = $first?->id;
    }

    protected function canAccessChannel(Channel $channel): bool
    {
        return ! $channel->is_private || $channel->hasMember(Auth::id());
    }

    public function visibleChannels()
    {
        return Channel::query()
            ->where(function ($q) {
                $q->where('is_private', false)
                    ->orWhereHas('users', fn ($q2) => $q2->where('users.id', Auth::id()));
            })
            ->orderBy('name')
            ->get();
    }

    public function colleagues()
    {
        return User::where('id', '!=', Auth::id())->orderBy('name')->get();
    }

    public function conversations()
    {
        $me = Auth::id();

        $rows = Message::query()
            ->selectRaw('CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as partner_id', [$me])
            ->selectRaw('MAX(created_at) as last_at')
            ->selectRaw('SUM(CASE WHEN receiver_id = ? AND read_at IS NULL THEN 1 ELSE 0 END) as unread', [$me])
            ->whereNotNull('receiver_id')
            ->where(fn ($q) => $q->where('sender_id', $me)->orWhere('receiver_id', $me))
            ->groupBy('partner_id')
            ->orderByDesc('last_at')
            ->get();

        $users = User::whereIn('id', $rows->pluck('partner_id')->filter())->get()->keyBy('id');

        return $rows
            ->map(fn ($r) => (object) [
                'user' => $users->get($r->partner_id),
                'unread' => (int) $r->unread,
            ])
            ->filter(fn ($r) => $r->user !== null)
            ->values();
    }

    public function totalUnread(): int
    {
        return (int) Message::whereNotNull('receiver_id')
            ->where('receiver_id', Auth::id())
            ->whereNull('read_at')
            ->count();
    }

    public function searchResults()
    {
        $term = trim($this->userSearch);

        if ($term === '') {
            return collect();
        }

        return User::query()
            ->where('id', '!=', Auth::id())
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('title', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(25)
            ->get();
    }

    public function activeChannel(): ?Channel
    {
        return $this->activeChannelId ? Channel::find($this->activeChannelId) : null;
    }

    public function activeUser(): ?User
    {
        return $this->activeUserId ? User::find($this->activeUserId) : null;
    }

    public function profileUser(): ?User
    {
        return $this->profileUserId ? User::find($this->profileUserId) : null;
    }

    public function currentMessages()
    {
        if ($this->activeType === 'channel' && $this->activeChannelId) {
            $channel = $this->activeChannel();

            if (! $channel || ! $this->canAccessChannel($channel)) {
                return collect();
            }

            return Message::with('sender')
                ->where('channel_id', $this->activeChannelId)
                ->orderBy('created_at')
                ->get();
        }

        if ($this->activeType === 'dm' && $this->activeUserId) {
            $me = Auth::id();
            $them = $this->activeUserId;

            return Message::with('sender')
                ->where(function ($q) use ($me, $them) {
                    $q->where('sender_id', $me)->where('receiver_id', $them);
                })
                ->orWhere(function ($q) use ($me, $them) {
                    $q->where('sender_id', $them)->where('receiver_id', $me);
                })
                ->orderBy('created_at')
                ->get();
        }

        return collect();
    }

    public function selectChannel(int $channelId): void
    {
        $channel = Channel::findOrFail($channelId);

        if (! $this->canAccessChannel($channel)) {
            $this->addError('access', 'Немаш пристап до овој канал.');

            return;
        }

        $this->activeType = 'channel';
        $this->activeChannelId = $channelId;
        $this->activeUserId = null;
        $this->resetEditing();
    }

    public function selectUser(int $userId): void
    {
        $this->activeType = 'dm';
        $this->activeUserId = $userId;
        $this->activeChannelId = null;
        $this->resetEditing();
        $this->markThreadRead($userId);
    }

    protected function markThreadRead(int $userId): void
    {
        Message::where('sender_id', $userId)
            ->where('receiver_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function sendMessage(): void
    {
        $this->validate([
            'newMessage' => ['required', 'string', 'max:5000'],
        ]);

        if ($this->activeType === 'channel' && $this->activeChannelId) {
            $channel = Channel::findOrFail($this->activeChannelId);

            if (! $this->canAccessChannel($channel)) {
                $this->addError('access', 'Немаш пристап до овој канал.');

                return;
            }

            Message::create([
                'body' => $this->newMessage,
                'sender_id' => Auth::id(),
                'channel_id' => $channel->id,
            ]);
        } elseif ($this->activeType === 'dm' && $this->activeUserId) {
            Message::create([
                'body' => $this->newMessage,
                'sender_id' => Auth::id(),
                'receiver_id' => $this->activeUserId,
            ]);
        } else {
            return;
        }

        $this->newMessage = '';
        $this->dispatch('message-sent');
    }

    public function startEdit(int $messageId): void
    {
        $message = Message::findOrFail($messageId);

        if ($message->sender_id !== Auth::id()) {
            return;
        }

        $this->editingMessageId = $messageId;
        $this->editingBody = $message->body;
    }

    public function saveEdit(): void
    {
        if (! $this->editingMessageId) {
            return;
        }

        $message = Message::findOrFail($this->editingMessageId);

        if ($message->sender_id !== Auth::id()) {
            return;
        }

        $this->validate(['editingBody' => ['required', 'string', 'max:5000']]);

        $message->update([
            'body' => $this->editingBody,
            'is_edited' => true,
        ]);

        $this->resetEditing();
    }

    public function cancelEdit(): void
    {
        $this->resetEditing();
    }

    protected function resetEditing(): void
    {
        $this->editingMessageId = null;
        $this->editingBody = '';
    }

    public function deleteMessage(int $messageId): void
    {
        $message = Message::findOrFail($messageId);

        if ($message->sender_id !== Auth::id() && ! Auth::user()->isAdmin()) {
            return;
        }

        $message->delete();
    }

    public function toggleCreateChannel(): void
    {
        $this->showCreateChannel = ! $this->showCreateChannel;
        $this->resetValidation();
    }

    public function toggleMember(int $userId): void
    {
        if (in_array($userId, $this->newChannelMembers, true)) {
            $this->newChannelMembers = array_values(array_diff($this->newChannelMembers, [$userId]));
        } else {
            $this->newChannelMembers[] = $userId;
        }
    }

    public function createChannel(): void
    {
        $this->validate([
            'newChannelName' => ['required', 'string', 'max:80', 'unique:channels,name'],
            'newChannelDescription' => ['nullable', 'string', 'max:255'],
        ]);

        $channel = Channel::create([
            'name' => Str::slug($this->newChannelName, '-') ?: Str::lower(Str::random(6)),
            'slug' => Str::slug($this->newChannelName),
            'description' => $this->newChannelDescription,
            'is_private' => $this->newChannelPrivate,
            'created_by' => Auth::id(),
        ]);

        $memberIds = collect($this->newChannelMembers)->push(Auth::id())->unique();

        $channel->users()->attach(
            $memberIds->mapWithKeys(fn ($id) => [$id => ['role' => $id === Auth::id() ? 'owner' : 'member']])
        );

        $this->newChannelName = '';
        $this->newChannelDescription = '';
        $this->newChannelPrivate = false;
        $this->newChannelMembers = [];
        $this->showCreateChannel = false;

        $this->selectChannel($channel->id);
    }

    public function openProfile(int $userId): void
    {
        $this->profileUserId = $userId;
        $this->showProfile = true;
    }

    public function closeProfile(): void
    {
        $this->showProfile = false;
        $this->profileUserId = null;
    }

    public function messageUser(int $userId): void
    {
        $this->closeProfile();

        if ($userId === Auth::id()) {
            return;
        }

        $this->selectUser($userId);
    }

    public function openEditProfile(): void
    {
        $user = Auth::user();

        $this->pName = $user->name;
        $this->pEmail = $user->email;
        $this->pTitle = (string) $user->title;
        $this->pBio = (string) $user->bio;
        $this->pAvatar = null;
        $this->pCurrentPassword = '';
        $this->pPassword = '';
        $this->pPasswordConfirmation = '';

        $this->resetValidation();
        $this->showProfile = false;
        $this->showEditProfile = true;
    }

    public function closeEditProfile(): void
    {
        $this->showEditProfile = false;
        $this->pAvatar = null;
    }

    public function saveProfile(): void
    {
        $user = Auth::user();

        $this->validate([
            'pName' => ['required', 'string', 'max:255'],
            'pEmail' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'pTitle' => ['nullable', 'string', 'max:120'],
            'pBio' => ['nullable', 'string', 'max:255'],
            'pAvatar' => ['nullable', 'image', 'max:2048'],
            'pPassword' => ['nullable', 'same:pPasswordConfirmation', Password::defaults()],
        ], [], [
            'pName' => 'име',
            'pEmail' => 'е-пошта',
            'pTitle' => 'позиција',
            'pBio' => 'опис',
            'pAvatar' => 'слика',
            'pPassword' => 'лозинка',
        ]);

        if (filled($this->pPassword)) {
            if (! Hash::check($this->pCurrentPassword, $user->password)) {
                $this->addError('pCurrentPassword', 'Тековната лозинка не е точна.');

                return;
            }

            $user->password = Hash::make($this->pPassword);
        }

        if ($this->pAvatar) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $user->avatar_path = $this->pAvatar->store('avatars', 'public');
        }

        $newEmail = Str::lower(trim($this->pEmail));
        $emailChanged = $newEmail !== Str::lower($user->email);

        $user->name = $this->pName;
        $user->title = $this->pTitle ?: null;
        $user->bio = $this->pBio ?: null;

        if ($emailChanged) {
            $user->email = $newEmail;
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
            $this->flash = 'Профилот е зачуван. Испративме нова врска за потврда на новата е-пошта.';
        } else {
            $this->flash = 'Профилот е зачуван.';
        }

        $this->pAvatar = null;
        $this->pCurrentPassword = '';
        $this->pPassword = '';
        $this->pPasswordConfirmation = '';
        $this->showEditProfile = false;

        $this->dispatch('flash', message: $this->flash);
    }

    protected function touchPresence(): void
    {
        User::whereKey(Auth::id())->update(['last_seen_at' => now()]);
    }

    public function heartbeat(): void
    {
        $this->touchPresence();

        $me = Auth::id();

        $incoming = Message::with('sender')
            ->whereNotNull('receiver_id')
            ->where('receiver_id', $me)
            ->where('sender_id', '!=', $me)
            ->where('id', '>', $this->notifiedThroughId)
            ->orderBy('id')
            ->get();

        if ($incoming->isNotEmpty()) {
            foreach ($incoming as $msg) {
                $viewingThisThread = $this->activeType === 'dm' && $this->activeUserId === $msg->sender_id;

                if (! $viewingThisThread) {
                    $this->dispatch('dm-notification',
                        userId: $msg->sender_id,
                        name: $msg->sender->name,
                        body: Str::limit($msg->body, 90),
                        color: $msg->sender->avatar_color,
                        initials: $msg->sender->initials(),
                        avatar: $msg->sender->avatarUrl(),
                    );
                }
            }

            $this->notifiedThroughId = (int) $incoming->max('id');
        }

        if ($this->activeType === 'dm' && $this->activeUserId) {
            $this->markThreadRead($this->activeUserId);
        }
    }

    public function render()
    {
        $total = $this->totalUnread();

        $this->dispatch('unread-total', count: $total);

        return view('livewire.chat', [
            'channels' => $this->visibleChannels(),
            'colleagues' => $this->colleagues(),
            'conversations' => $this->conversations(),
            'people' => $this->searchResults(),
            'messages' => $this->currentMessages(),
            'openChannel' => $this->activeChannel(),
            'openUser' => $this->activeUser(),
            'profile' => $this->profileUser(),
            'totalUnread' => $total,
        ]);
    }
}
