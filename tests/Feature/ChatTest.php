<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use App\Services\Fit\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function user(array $attributes = []): User
    {
        $user = User::factory()->create(['status' => true, ...$attributes]);
        $user->assignRole(Role::findOrCreate('user', 'web'));

        return $user->fresh();
    }

    /** @return array{0: User, 1: User} */
    private function friends(): array
    {
        $ana = $this->user(['name' => 'Ana']);
        $bogdan = $this->user(['name' => 'Bogdan']);
        Friendship::create(['user_id' => $ana->id, 'friend_id' => $bogdan->id, 'status' => 'accepted', 'accepted_at' => now()]);

        return [$ana, $bogdan];
    }

    private function say(User $from, User $to, string $body): int
    {
        return $this->actingAs($from)->postJson("/chat/{$to->id}", ['body' => $body])->assertCreated()->json('message.id');
    }

    public function test_friends_can_chat_and_opening_the_chat_marks_messages_read(): void
    {
        [$ana, $bogdan] = $this->friends();

        $this->say($ana, $bogdan, "  Salut!\nCe faci?  ");

        $this->assertSame(1, $bogdan->unreadMessagesCount());
        $this->actingAs($bogdan)->get('/friends')->assertInertia(fn ($page) => $page
            ->where('friends.0.unread', 1)
            ->where('friends.0.lastMessage.body', "Salut!\nCe faci?")
            ->where('friends.0.lastMessage.mine', false)
            ->where('friendsBadge', 1));

        $this->actingAs($bogdan)->get("/chat/{$ana->id}")->assertInertia(fn ($page) => $page
            ->component('Fit/Chat')
            ->where('friend.name', 'Ana')
            ->where('messages.0.body', "Salut!\nCe faci?")
            ->where('messages.0.mine', false));

        $this->assertSame(0, $bogdan->unreadMessagesCount());
        $this->actingAs($ana)->getJson("/chat/{$bogdan->id}/messages?after=0")->assertJsonPath('readUpTo', Message::first()->id);
    }

    public function test_polling_returns_only_newer_messages_and_pages_back_through_history(): void
    {
        [$ana, $bogdan] = $this->friends();

        $ids = collect(range(1, 55))->map(fn ($i) => Message::create(['sender_id' => $ana->id, 'recipient_id' => $bogdan->id, 'body' => "m{$i}"])->id);

        $this->actingAs($bogdan)->get("/chat/{$ana->id}")->assertInertia(fn ($page) => $page
            ->has('messages', 50)
            ->where('messages.0.body', 'm6')
            ->where('messages.49.body', 'm55')
            ->where('hasMore', true));

        $this->actingAs($bogdan)->getJson("/chat/{$ana->id}/messages?before={$ids[5]}")
            ->assertJsonCount(5, 'messages')
            ->assertJsonPath('messages.0.body', 'm1')
            ->assertJsonPath('hasMore', false);

        $new = $this->say($ana, $bogdan, 'nou');

        $this->actingAs($bogdan)->getJson("/chat/{$ana->id}/messages?after={$ids->last()}")
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.id', $new);
    }

    public function test_only_friends_can_talk_or_read(): void
    {
        [$ana, $bogdan] = $this->friends();
        $stranger = $this->user();
        $this->say($ana, $bogdan, 'secret');

        $this->actingAs($stranger)->postJson("/chat/{$ana->id}", ['body' => 'hei'])->assertNotFound();
        $this->actingAs($stranger)->getJson("/chat/{$ana->id}/messages")->assertNotFound();
        $this->actingAs($stranger)->get("/chat/{$ana->id}")->assertRedirect('/friends');

        // once the friendship ends the conversation closes for both
        Friendship::query()->delete();
        $this->actingAs($ana)->postJson("/chat/{$bogdan->id}", ['body' => 'mai ești?'])->assertNotFound();
        $this->actingAs($bogdan)->get("/chat/{$ana->id}")->assertRedirect('/friends');
    }

    public function test_empty_and_too_long_messages_are_rejected(): void
    {
        [$ana, $bogdan] = $this->friends();

        $this->actingAs($ana)->postJson("/chat/{$bogdan->id}", ['body' => '   '])->assertJsonValidationErrors(['body' => 'Scrie un mesaj.']);
        $this->actingAs($ana)->postJson("/chat/{$bogdan->id}", ['body' => str_repeat('a', 2001)])->assertJsonValidationErrors('body');
        $this->assertSame(0, Message::count());
    }

    public function test_push_is_sent_only_when_the_recipient_is_not_in_the_chat(): void
    {
        [$ana, $bogdan] = $this->friends();

        $this->mock(PushSender::class, function ($mock) use ($bogdan) {
            $mock->shouldReceive('send')->once()->withArgs(fn (User $to, array $message) => $to->is($bogdan)
                && $message['title'] === 'Ana'
                && ! str_contains($message['body'], 'prima')
                && $message['url'] === '/chat/'.User::where('name', 'Ana')->value('id'))->andReturn(1);
        });

        $this->say($ana, $bogdan, 'prima');

        // Bogdan opens the conversation, so the next message stays silent
        $this->actingAs($bogdan)->getJson("/chat/{$ana->id}/messages?after=0");
        $this->assertTrue(Cache::has("chat-open:{$bogdan->id}:{$ana->id}"));
        $this->say($ana, $bogdan, 'a doua');
    }

    public function test_received_messages_can_be_reported_once(): void
    {
        [$ana, $bogdan] = $this->friends();
        $mine = $this->say($bogdan, $ana, 'al meu');
        $theirs = $this->say($ana, $bogdan, 'ceva urât');

        $this->actingAs($bogdan)->postJson("/chat/messages/{$mine}/report")->assertNotFound();
        $this->actingAs($this->user())->postJson("/chat/messages/{$theirs}/report")->assertNotFound();

        $this->actingAs($bogdan)->postJson("/chat/messages/{$theirs}/report", ['reason' => 'jignitor'])->assertOk();
        $this->actingAs($bogdan)->postJson("/chat/messages/{$theirs}/report")->assertOk();

        $report = MessageReport::sole();
        $this->assertSame('ceva urât', $report->body);
        $this->assertSame('jignitor', $report->reason);
        $this->assertSame($bogdan->id, $report->reporter_id);
    }

    public function test_blocking_from_the_chat_ends_it(): void
    {
        [$ana, $bogdan] = $this->friends();
        $this->say($ana, $bogdan, 'hei');

        $this->actingAs($bogdan)->post("/blocks/{$ana->id}")->assertRedirect('/friends');

        $this->actingAs($ana)->postJson("/chat/{$bogdan->id}", ['body' => 'alo?'])->assertNotFound();
        $this->assertSame(1, Message::count());
    }

    public function test_messages_and_reports_are_encrypted_in_the_database(): void
    {
        [$ana, $bogdan] = $this->friends();
        $id = $this->say($ana, $bogdan, 'parola mea e 1234');
        $this->actingAs($bogdan)->postJson("/chat/messages/{$id}/report", ['reason' => 'date personale']);

        $raw = DB::table('messages')->value('body');
        $report = DB::table('message_reports')->first();

        $this->assertStringNotContainsString('parola', $raw);
        $this->assertStringNotContainsString('parola', $report->body);
        $this->assertStringNotContainsString('personale', $report->reason);
        $this->assertSame('parola mea e 1234', Message::find($id)->body);
    }

    public function test_conversations_stay_closed_while_an_admin_impersonates(): void
    {
        [$ana, $bogdan] = $this->friends();
        $this->say($ana, $bogdan, 'doar pentru tine');

        $session = ['impersonate' => $this->user()->id];

        $this->actingAs($bogdan)->withSession($session)->get("/chat/{$ana->id}")->assertRedirect('/friends');
        $this->actingAs($bogdan)->withSession($session)->getJson("/chat/{$ana->id}/messages")->assertForbidden();
        $this->actingAs($bogdan)->withSession($session)->postJson("/chat/{$ana->id}", ['body' => 'x'])->assertForbidden();

        $this->actingAs($bogdan)->withSession($session)->get('/friends')
            ->assertInertia(fn ($page) => $page->where('friends.0.lastMessage.body', '🔒 Mesaj privat'))
            ->assertDontSee('doar pentru tine');

        // nothing was marked read on Bogdan's behalf
        $this->assertSame(1, $bogdan->unreadMessagesCount());
    }
}
