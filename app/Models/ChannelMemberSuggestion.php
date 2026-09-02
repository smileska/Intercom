<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelMemberSuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id',
        'user_id',
        'suggested_by',
        'status',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function suggestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_by');
    }
}
