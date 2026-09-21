<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_guests_are_sent_to_login_from_the_root(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
