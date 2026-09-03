<?php

namespace App\Livewire;

use App\Models\Channel;
use App\Models\ChannelMemberSuggestion;
use App\Models\Message;
use App\Models\MessageReaction;
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

    public bool $showChannelMembers = false;

    public ?int $membersChannelId = null;

    public $composerImage = null;

    public function mount(?Channel $initialChannel = null, ?User $initialUser = null): void
    {
        $this->notifiedThroughId = (int) (Message::whereNotNull('receiver_id')
            ->where('receiver_id', Auth::id())
            ->max('id') ?? 0);

        User::whereKey(Auth::id())->update(['last_seen_at' => now(), 'last_active_at' => now()]);

        if ($initialUser && $initialUser->exists) {
            $this->activeType = 'dm';
            $this->activeUserId = $initialUser->id;
            $this->markThreadRead($initialUser->id);

            return;
        }

        if ($initialChannel && $initialChannel->exists && $this->canAccessChannel($initialChannel)) {
            $this->activeType = 'channel';
            $this->activeChannelId = $initialChannel->id;
            $this->markChannelRead($initialChannel->id);

            return;
        }

        $first = $this->visibleChannels()->first();
        $this->activeChannelId = $first?->id;
    }

    protected function canAccessChannel(Channel $channel): bool
    {
        return Auth::user()->isAdmin()
            || ! $channel->is_private
            || $channel->hasMember(Auth::id());
    }

    public function visibleChannels()
    {
        if (Auth::user()->isAdmin()) {
            return Channel::orderBy('name')->get();
        }

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

            return Message::with('sender', 'reactions')
                ->where('channel_id', $this->activeChannelId)
                ->orderBy('created_at')
                ->get();
        }

        if ($this->activeType === 'dm' && $this->activeUserId) {
            $me = Auth::id();
            $them = $this->activeUserId;

            return Message::with('sender', 'reactions')
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
        $this->markChannelRead($channelId);
    }

    protected function markChannelRead(int $channelId): void
    {
        Channel::find($channelId)?->users()
            ->updateExistingPivot(Auth::id(), ['last_read_at' => now()]);
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
            'newMessage' => ['nullable', 'string', 'max:5000'],
            'composerImage' => ['nullable', 'image', 'max:5120'],
        ]);

        if (trim($this->newMessage) === '' && ! $this->composerImage) {
            $this->addError('newMessage', 'Напиши порака или прикачи слика.');

            return;
        }

        $imagePath = $this->composerImage
            ? $this->composerImage->store('chat-images', 'public')
            : null;

        $payload = [
            'body' => $this->newMessage,
            'image_path' => $imagePath,
            'sender_id' => Auth::id(),
        ];

        if ($this->activeType === 'channel' && $this->activeChannelId) {
            $channel = Channel::findOrFail($this->activeChannelId);

            if (! $this->canAccessChannel($channel)) {
                $this->addError('access', 'Немаш пристап до овој канал.');

                return;
            }

            Message::create($payload + ['channel_id' => $channel->id]);
        } elseif ($this->activeType === 'dm' && $this->activeUserId) {
            Message::create($payload + ['receiver_id' => $this->activeUserId]);
        } else {
            return;
        }

        $this->newMessage = '';
        $this->composerImage = null;
        $this->dispatch('message-sent');
    }

    public function toggleReaction(int $messageId, string $emoji): void
    {
        $emoji = trim($emoji);

        if ($emoji === '' || mb_strlen($emoji) > 8) {
            return;
        }

        $message = Message::find($messageId);

        if (! $message || ! $this->canSeeMessage($message)) {
            return;
        }

        $existing = MessageReaction::where('message_id', $messageId)
            ->where('user_id', Auth::id())
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();

            return;
        }

        MessageReaction::create([
            'message_id' => $messageId,
            'user_id' => Auth::id(),
            'emoji' => $emoji,
        ]);
    }

    protected function canSeeMessage(Message $message): bool
    {
        if ($message->channel_id) {
            $channel = Channel::find($message->channel_id);

            return $channel && $this->canAccessChannel($channel);
        }

        return in_array(Auth::id(), [$message->sender_id, $message->receiver_id], true);
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

    public function toggleAdmin(int $userId): void
    {
        if (! Auth::user()->isAdmin() || $userId === Auth::id()) {
            return;
        }

        $user = User::findOrFail($userId);

        if ($user->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            $this->addError('role', 'Мора да остане барем еден администратор.');

            return;
        }

        $user->update(['role' => $user->isAdmin() ? 'employee' : 'admin']);
        $this->resetValidation('role');
    }

    public function openChannelMembers(int $channelId): void
    {
        $channel = Channel::findOrFail($channelId);

        if (! $this->canAccessChannel($channel)) {
            return;
        }

        $this->membersChannelId = $channelId;
        $this->showChannelMembers = true;
        $this->resetValidation();
    }

    public function closeChannelMembers(): void
    {
        $this->showChannelMembers = false;
        $this->membersChannelId = null;
    }

    protected function membersChannel(): ?Channel
    {
        return $this->membersChannelId ? Channel::find($this->membersChannelId) : null;
    }

    protected function openMembersModalChannel(int $channelId): ?Channel
    {
        if ($channelId !== $this->membersChannelId) {
            return null;
        }

        return Channel::find($channelId);
    }

    public function addChannelMember(int $channelId, int $userId): void
    {
        $channel = $this->openMembersModalChannel($channelId);

        if (! Auth::user()->isAdmin() || ! $channel) {
            return;
        }

        $channel->users()->syncWithoutDetaching([$userId => ['role' => 'member']]);

        ChannelMemberSuggestion::where('channel_id', $channel->id)
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->update(['status' => 'approved']);
    }

    public function removeChannelMember(int $channelId, int $userId): void
    {
        $channel = $this->openMembersModalChannel($channelId);

        if (! Auth::user()->isAdmin() || ! $channel) {
            return;
        }

        if ($channel->created_by === $userId) {
            $this->addError('members', 'Креаторот на каналот не може да се отстрани.');

            return;
        }

        $channel->users()->detach($userId);

        if ($userId === Auth::id() && $this->activeChannelId === $channel->id && ! $this->canAccessChannel($channel)) {
            $this->closeChannelMembers();
            $this->activeChannelId = $this->visibleChannels()->first()?->id;
            $this->activeType = 'channel';
        }
    }

    public function suggestMember(int $channelId, int $userId): void
    {
        $channel = $this->openMembersModalChannel($channelId);

        if (! $channel || ! $this->canAccessChannel($channel) || $channel->hasMember($userId)) {
            return;
        }

        ChannelMemberSuggestion::updateOrCreate(
            ['channel_id' => $channel->id, 'user_id' => $userId],
            ['suggested_by' => Auth::id(), 'status' => 'pending'],
        );

        $this->flash = 'Предлогот е испратен до администраторите.';
        $this->dispatch('flash', message: $this->flash);
    }

    public function approveSuggestion(int $suggestionId): void
    {
        if (! Auth::user()->isAdmin()) {
            return;
        }

        $suggestion = ChannelMemberSuggestion::findOrFail($suggestionId);

        $suggestion->channel->users()->syncWithoutDetaching([
            $suggestion->user_id => ['role' => 'member'],
        ]);

        $suggestion->update(['status' => 'approved']);
    }

    public function rejectSuggestion(int $suggestionId): void
    {
        if (! Auth::user()->isAdmin()) {
            return;
        }

        ChannelMemberSuggestion::whereKey($suggestionId)->update(['status' => 'rejected']);
    }

    public function channelMemberRows()
    {
        $channel = $this->membersChannel();

        return $channel ? $channel->users()->orderBy('name')->get() : collect();
    }

    public function channelCandidates()
    {
        $channel = $this->membersChannel();

        if (! $channel) {
            return collect();
        }

        $memberIds = $channel->users()->pluck('users.id');

        return User::whereNotIn('id', $memberIds)->orderBy('name')->get();
    }

    public function channelPendingSuggestions()
    {
        $channel = $this->membersChannel();

        if (! $channel) {
            return collect();
        }

        return ChannelMemberSuggestion::with('user', 'suggestedBy')
            ->where('channel_id', $channel->id)
            ->where('status', 'pending')
            ->latest()
            ->get();
    }

    public function pendingSuggestionCountByChannel()
    {
        return ChannelMemberSuggestion::where('status', 'pending')
            ->selectRaw('channel_id, count(*) as total')
            ->groupBy('channel_id')
            ->pluck('total', 'channel_id');
    }

    protected function channelSeenBy(): array
    {
        if ($this->activeType !== 'channel' || ! $this->activeChannelId) {
            return ['count' => 0, 'users' => collect()];
        }

        $channel = $this->activeChannel();

        $last = $channel
            ? Message::where('channel_id', $channel->id)->orderByDesc('id')->first()
            : null;

        if (! $last) {
            return ['count' => 0, 'users' => collect()];
        }

        $seers = $channel->users()
            ->where('users.id', '!=', Auth::id())
            ->where('users.id', '!=', $last->sender_id)
            ->wherePivot('last_read_at', '>=', $last->created_at)
            ->orderBy('name')
            ->get();

        return ['count' => $seers->count(), 'users' => $seers->take(5)];
    }

    protected function lastReadDmId(): ?int
    {
        if ($this->activeType !== 'dm' || ! $this->activeUserId) {
            return null;
        }

        return Message::where('sender_id', Auth::id())
            ->where('receiver_id', $this->activeUserId)
            ->whereNotNull('read_at')
            ->max('id');
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

    public function setStatus(?string $status): void
    {
        if ($status !== null && ! in_array($status, User::STATUSES, true)) {
            return;
        }

        Auth::user()->update(['status' => $status]);
    }

    public function reportActive(): void
    {
        User::whereKey(Auth::id())->update(['last_active_at' => now()]);
    }

    public function heartbeat(): void
    {
        $this->touchPresence();

        $me = Auth::id();
        $muted = Auth::user()->presence() === 'dnd';

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

                if (! $viewingThisThread && ! $muted) {
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

        if ($this->activeType === 'channel' && $this->activeChannelId) {
            $this->markChannelRead($this->activeChannelId);
        }
    }

    public function render()
    {
        $total = $this->totalUnread();

        $this->dispatch('unread-total', count: $total);

        $showMembers = $this->showChannelMembers;
        $pendingSuggestions = $showMembers ? $this->channelPendingSuggestions() : collect();

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
            'membersChannel' => $showMembers ? $this->membersChannel() : null,
            'memberRows' => $showMembers ? $this->channelMemberRows() : collect(),
            'candidates' => $showMembers ? $this->channelCandidates() : collect(),
            'pendingSuggestions' => $pendingSuggestions,
            'suggestedUserIds' => $pendingSuggestions->pluck('user_id')->all(),
            'pendingByChannel' => Auth::user()->isAdmin() ? $this->pendingSuggestionCountByChannel() : collect(),
            'quickEmojis' => config('emojis.quick'),
            'emojiList' => config('emojis.all'),
            'channelSeenBy' => $this->channelSeenBy(),
            'lastReadDmId' => $this->lastReadDmId(),
        ]);
    }
}
