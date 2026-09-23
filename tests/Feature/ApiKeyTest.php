<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Models\User;
use App\Services\Anthropic\Models;
use App\Services\Calories\FoodPhotoAnalyzer;
use App\Services\Fit\NutritionAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ApiKeyTest extends TestCase
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

    public function test_ai_features_are_closed_without_a_key(): void
    {
        $this->mock(FoodPhotoAnalyzer::class)->shouldNotReceive('analyze');
        $this->mock(NutritionAssistant::class)->shouldNotReceive('ask');
        $user = $this->user();

        $this->actingAs($user)->postJson('/scan/analyze', ['photo' => UploadedFile::fake()->image('meal.jpg')])
            ->assertForbidden()->assertJsonPath('missing_api_key', true);
        $this->actingAs($user)->postJson('/meals/analyze-text', ['description' => 'două ouă'])->assertForbidden();
        $this->actingAs($user)->postJson('/assistant/ask', ['message' => 'Ce mănânc?'])->assertForbidden();
        $this->actingAs($user)->postJson('/workout/ask', ['message' => 'Picioare azi'])->assertForbidden();
    }

    public function test_a_key_can_be_saved_encrypted_and_removed(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/me/api-key', ['apiKey' => 'not-a-key'])->assertSessionHasErrors('apiKey');

        $this->actingAs($user)->put('/me/api-key', ['apiKey' => ' sk-ant-secret-abcd '])->assertRedirect();
        $this->assertSame('sk-ant-secret-abcd', $user->fresh()->anthropic_api_key);
        $this->assertStringNotContainsString('sk-ant', DB::table('users')->where('id', $user->id)->value('anthropic_api_key'));

        $this->actingAs($user)->get('/me')->assertInertia(fn ($page) => $page->where('apiKeyHint', '…abcd'));
        $this->assertArrayNotHasKey('anthropic_api_key', $user->fresh()->toArray());

        $this->actingAs($user)->delete('/me/api-key')->assertRedirect();
        $this->assertNull($user->fresh()->anthropic_api_key);
    }

    public function test_the_client_uses_the_logged_in_users_key(): void
    {
        $alice = $this->user(['anthropic_api_key' => 'sk-ant-alice']);
        $bob = $this->user(['anthropic_api_key' => 'sk-ant-bob']);

        $this->actingAs($alice);
        $this->assertSame('sk-ant-alice', app(Client::class)->apiKey);

        $this->actingAs($bob);
        $this->assertSame('sk-ant-bob', app(Client::class)->apiKey);

        // never fall back to credentials found on the server
        $this->actingAs($this->user());
        $this->expectException(HttpException::class);
        app(Client::class);
    }

    public function test_a_model_can_be_picked_from_the_offered_list(): void
    {
        config(['services.anthropic.model' => 'claude-sonnet-5']);
        $user = $this->user();

        $this->actingAs($user)->get('/me')->assertInertia(fn ($page) => $page
            ->where('aiModel', 'claude-sonnet-5')
            ->has('aiModels.claude-haiku-4-5'));

        $this->actingAs($user)->put('/me/ai-model', ['model' => 'gpt-4'])->assertSessionHasErrors('model');
        $this->assertSame('claude-sonnet-5', $user->fresh()->anthropicModel());

        $this->actingAs($user)->put('/me/ai-model', ['model' => 'claude-opus-5'])->assertRedirect();
        $this->assertSame('claude-opus-5', $user->fresh()->anthropicModel());
    }

    public function test_haiku_is_called_without_effort(): void
    {
        $schema = ['type' => 'object'];

        $this->assertArrayNotHasKey('effort', Models::outputConfig('claude-haiku-4-5', 'low', $schema));
        $this->assertSame('low', Models::outputConfig('claude-sonnet-5', 'low', $schema)['effort']);
    }
}
