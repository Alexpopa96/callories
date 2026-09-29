<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageReport extends Model
{
    protected $fillable = ['reporter_id', 'message_id', 'body', 'reason', 'resolved_at'];

    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
            'reason' => 'encrypted',
            'resolved_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
