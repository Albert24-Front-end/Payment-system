<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class HealthTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function testHealth(): void
    {
        $response = $this->get('/api/health');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }
}
