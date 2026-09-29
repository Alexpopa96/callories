<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Events\MessagesRead;
use App\Models\Friendship;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function user(): User
    {
        $user = User::factory()->create(['status' => true]);
        $user->assignRole(Role::findOrCreate('user', 'web'));

        return $user->fresh();
    }

    /** @return array{0: User, 1: User} */
    private function friends(): array
    {
        [$ana, $bogdan] = [$this->user(), $this->user()];
        Friendship::create(['user_id' => $ana->id, 'friend_id' => $bogdan->id, 'status' => 'accepted', 'accepted_at' => now()]);

        return [$ana, $bogdan];
    }

    public function test_sending_announces_the_message_to_both_people_without_its_text(): void
    {
        Event::fake([MessageSent::class]);
        [$ana, $bogdan] = $this->friends();

        $id = $this->actingAs($ana)->postJson("/chat/{$bogdan->id}", ['body' => 'secret'])->assertCreated()->json('message.id');

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($ana, $bogdan, $id) {
            $channels = collect($event->broadcastOn())->map->name->all();

            return $channels === ["private-App.Models.User.{$bogdan->id}", "private-App.Models.User.{$ana->id}"]
                && $event->broadcastWith() === ['id' => $id, 'senderId' => $ana->id, 'recipientId' => $bogdan->id]
                && ! str_contains(json_encode($event->broadcastWith()), 'secret');
        });
    }

    public function test_reading_turns_the_senders_ticks_blue_only_when_something_was_unread(): void
    {
        Event::fake([MessagesRead::class]);
        [$ana, $bogdan] = $this->friends();
        Message::create(['sender_id' => $ana->id, 'recipient_id' => $bogdan->id, 'body' => 'unu']);
        $last = Message::create(['sender_id' => $ana->id, 'recipient_id' => $bogdan->id, 'body' => 'doi']);

        $this->actingAs($bogdan)->get("/chat/{$ana->id}")->assertOk();

        Event::assertDispatchedTimes(MessagesRead::class, 1);
        Event::assertDispatched(MessagesRead::class, fn (MessagesRead $event) => $event->readerId === $bogdan->id
            && $event->senderId === $ana->id
            && $event->upTo === $last->id
            && $event->broadcastOn()[0]->name === "private-App.Models.User.{$ana->id}");

        // polling again with nothing new stays quiet
        $this->actingAs($bogdan)->getJson("/chat/{$ana->id}/messages?after={$last->id}")->assertOk();
        Event::assertDispatchedTimes(MessagesRead::class, 1);
    }

    public function test_users_can_only_listen_to_their_own_channel(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        // channels were registered on the null broadcaster at boot; register them on the real one
        require base_path('routes/channels.php');
        [$ana, $bogdan] = $this->friends();

        $this->actingAs($ana)->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-App.Models.User.{$ana->id}",
        ])->assertOk()->assertJsonStructure(['auth']);

        $this->actingAs($ana)->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-App.Models.User.{$bogdan->id}",
        ])->assertForbidden();
    }
}
