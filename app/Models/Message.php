<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = ['sender_id', 'recipient_id', 'client_id', 'body', 'meal', 'read_at'];

    protected function casts(): array
    {
        return [
            // stored encrypted with APP_KEY: a database dump or backup shows no conversations
            'body' => 'encrypted',
            'meal' => 'encrypted:array',
            'read_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /** Both directions of the conversation between two users. */
    public function scopeBetween(Builder $query, User $a, User $b): void
    {
        $query->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('sender_id', $a->id)->where('recipient_id', $b->id))
            ->orWhere(fn ($q) => $q->where('sender_id', $b->id)->where('recipient_id', $a->id)));
    }

    /**
     * What a friend sees of a shared meal: the numbers and the foods, never the photo
     * (neither the meal's nor a product's) or the notes.
     *
     * @return array{title: string, calories: int, grams: float, protein: float, carbs: float, fat: float, fiber: float, items: array<int, array{name: string, grams: float, calories: int}>}
     */
    public static function mealSnapshot(Meal $meal): array
    {
        $items = collect($meal->items ?? []);

        return [
            'title' => $meal->title,
            'calories' => (int) $meal->calories,
            'grams' => round($items->sum(fn (array $item) => (float) ($item['portion_grams'] ?? 0))),
            'protein' => round((float) $meal->protein_g, 1),
            'carbs' => round((float) $meal->carbs_g, 1),
            'fat' => round((float) $meal->fat_g, 1),
            'fiber' => round((float) $meal->fiber_g, 1),
            'items' => $items->map(fn (array $item) => [
                'name' => (string) ($item['name'] ?? ''),
                'grams' => round((float) ($item['portion_grams'] ?? 0)),
                'calories' => (int) round((float) ($item['calories'] ?? 0)),
            ])->values()->all(),
        ];
    }

    /** One line for the inbox and reports. */
    public function preview(): string
    {
        if (! $this->meal) {
            return $this->body;
        }

        $line = "🍽️ {$this->meal['title']} · {$this->meal['calories']} kcal";

        return $this->body === '' ? $line : "{$line} — {$this->body}";
    }

    /** @return array{id: int, clientId: ?string, body: string, meal: ?array, mine: bool, read: bool, time: string, day: string} */
    public function toChatArray(User $viewer): array
    {
        return [
            'id' => $this->id,
            // only the sender needs it, to match a send whose response got lost
            'clientId' => $this->sender_id === $viewer->id ? $this->client_id : null,
            'body' => $this->body,
            'meal' => $this->meal,
            'mine' => $this->sender_id === $viewer->id,
            'read' => $this->read_at !== null,
            'time' => $this->created_at->format('H:i'),
            'day' => $this->created_at->toDateString(),
        ];
    }
}
