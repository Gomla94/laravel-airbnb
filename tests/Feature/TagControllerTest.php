<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TagControllerTest extends TestCase
{
    /** @test */
    public function itListTags()
    {
        $response = $this->get('/api/tags');
        $response->assertStatus(200);
        $this->AssertCount(3, $response->json('data'));
        $this->assertNotNull($response->json('data')[0]['id']);
    }
}
