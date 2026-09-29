<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FriendsTest extends TestCase
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

    private function sendRequest(User $from, User $to): Friendship
    {
        $this->actingAs($from)->post('/friends', ['code' => $to->friendCode()])->assertRedirect('/friends');

        return Friendship::between($from, $to)->firstOrFail();
    }

    public function test_friends_page_creates_a_code_and_never_exposes_emails(): void
    {
        $ana = $this->user(['name' => 'Ana']);
        $bogdan = $this->user(['name' => 'Bogdan', 'email' => 'bogdan@example.com']);
        $this->sendRequest($bogdan, $ana);

        $response = $this->actingAs($ana)->get('/friends');

        $response->assertInertia(fn ($page) => $page
            ->component('Fit/Friends')
            ->where('code', $ana->fresh()->friend_code)
            ->where('incoming.0.name', 'Bogdan')
            ->missing('incoming.0.email'));
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{8}$/', $ana->fresh()->friend_code);
        $response->assertDontSee('bogdan@example.com');
    }

    public function test_a_request_is_accepted_by_the_receiver_only(): void
    {
        $ana = $this->user();
        $bogdan = $this->user();
        $friendship = $this->sendRequest($ana, $bogdan);

        $this->actingAs($ana)->post("/friends/{$friendship->id}/accept")->assertNotFound();
        $this->actingAs($bogdan)->post("/friends/{$friendship->id}/accept")->assertRedirect();

        $this->assertSame('accepted', $friendship->fresh()->status);
        $this->assertSame([$bogdan->id], $ana->friendIds());
        $this->assertSame([$ana->id], $bogdan->friendIds());
    }

    public function test_adding_the_code_of_someone_who_already_asked_accepts_them(): void
    {
        $ana = $this->user();
        $bogdan = $this->user();
        $friendship = $this->sendRequest($ana, $bogdan);

        $this->sendRequest($bogdan, $ana);

        $this->assertSame(1, Friendship::count());
        $this->assertSame('accepted', $friendship->fresh()->status);
    }

    public function test_codes_are_forgiving_about_case_and_spaces_but_reject_own_and_unknown(): void
    {
        $ana = $this->user();
        $bogdan = $this->user();
        $code = $bogdan->friendCode();

        $this->actingAs($ana)->post('/friends', ['code' => ' '.strtolower(substr($code, 0, 4)).' '.substr($code, 4)])->assertRedirect('/friends');
        $this->assertTrue(Friendship::between($ana, $bogdan)->exists());

        $this->actingAs($ana)->post('/friends', ['code' => $ana->friendCode()])->assertSessionHasErrors(['code' => 'Acesta e codul tău.']);
        $this->actingAs($ana)->post('/friends', ['code' => 'ZZZZZZZZ'])->assertSessionHasErrors(['code' => 'Codul nu există.']);
    }

    public function test_blocking_ends_the_friendship_and_hides_behind_an_unknown_code(): void
    {
        $ana = $this->user();
        $bogdan = $this->user();
        $friendship = $this->sendRequest($ana, $bogdan);
        $this->actingAs($bogdan)->post("/friends/{$friendship->id}/accept");

        $this->actingAs($bogdan)->post("/blocks/{$ana->id}")->assertRedirect();

        $this->assertSame(0, Friendship::count());
        $this->actingAs($ana)->post('/friends', ['code' => $bogdan->friendCode()])->assertSessionHasErrors(['code' => 'Codul nu există.']);
        $this->actingAs($bogdan)->post('/friends', ['code' => $ana->friendCode()])->assertSessionHasErrors('code');

        $this->actingAs($bogdan)->delete("/blocks/{$ana->id}")->assertRedirect();
        $this->sendRequest($ana, $bogdan);
    }

    public function test_strangers_cannot_be_blocked_or_have_their_requests_touched(): void
    {
        $ana = $this->user();
        $bogdan = $this->user();
        $stranger = $this->user();
        $friendship = $this->sendRequest($ana, $bogdan);

        $this->actingAs($stranger)->post("/blocks/{$ana->id}")->assertNotFound();
        $this->actingAs($stranger)->delete("/friends/{$friendship->id}")->assertNotFound();
        $this->actingAs($stranger)->post("/friends/{$friendship->id}/accept")->assertNotFound();

        $this->assertTrue($friendship->fresh()->exists);
    }

    public function test_either_side_can_remove_and_a_new_code_kills_old_links(): void
    {
        $ana = $this->user();
        $bogdan = $this->user();
        $friendship = $this->sendRequest($ana, $bogdan);

        $this->actingAs($bogdan)->delete("/friends/{$friendship->id}")->assertSessionHas('success', 'Cererea a fost refuzată.');
        $this->assertSame(0, Friendship::count());

        $oldCode = $bogdan->friendCode();
        $this->actingAs($bogdan)->post('/friends/code')->assertRedirect();

        $this->assertNotSame($oldCode, $bogdan->fresh()->friend_code);
        $this->actingAs($ana)->post('/friends', ['code' => $oldCode])->assertSessionHasErrors('code');
    }

    public function test_adding_by_code_is_rate_limited(): void
    {
        $ana = $this->user();

        foreach (range(1, 10) as $i) {
            $this->actingAs($ana)->post('/friends', ['code' => 'ZZZZZZZZ']);
        }

        $this->actingAs($ana)->post('/friends', ['code' => 'ZZZZZZZZ'])->assertStatus(429);
    }

    public function test_profile_shows_how_many_requests_are_waiting(): void
    {
        $ana = $this->user();
        $this->sendRequest($this->user(), $ana);
        $this->sendRequest($this->user(), $ana);

        $this->actingAs($ana)->get('/me')->assertInertia(fn ($page) => $page->where('friendsBadge', 2));
    }
}
