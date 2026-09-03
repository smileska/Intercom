<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'title',
        'avatar_color',
        'avatar_path',
        'bio',
        'last_seen_at',
        'last_active_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_active_at' => 'datetime',
        'password' => 'hashed',
    ];

    public const STATUSES = ['online', 'away', 'dnd', 'invisible'];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new QueuedVerifyEmail());
    }

    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class)
            ->withPivot('role', 'last_read_at')
            ->withTimestamps();
    }

    public function createdChannels(): HasMany
    {
        return $this->hasMany(Channel::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));
        $letters = array_map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_filter($parts));

        return implode('', array_slice($letters, 0, 2)) ?: '?';
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path
            ? Storage::disk('public')->url($this->avatar_path)
            : null;
    }

    public function presence(?int $viewerId = null): string
    {
        $self = $viewerId !== null && $viewerId === $this->id;

        $connected = $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subMinutes(5));

        if (! $connected) {
            return 'offline';
        }

        if ($this->status === 'invisible') {
            return $self ? 'invisible' : 'offline';
        }

        if ($this->status === 'dnd') {
            return 'dnd';
        }

        if ($this->status === 'away') {
            return 'away';
        }

        $idle = $this->last_active_at === null
            || $this->last_active_at->lt(now()->subMinutes(2));

        return $idle ? 'away' : 'online';
    }

    public function presenceLabel(?int $viewerId = null): string
    {
        return [
            'online' => 'Активен',
            'away' => 'Отсутен',
            'dnd' => 'Не вознемирувај',
            'invisible' => 'Невидлив',
            'offline' => 'Офлајн',
        ][$this->presence($viewerId)];
    }

    public function isOnline(): bool
    {
        return $this->presence() === 'online';
    }
}
