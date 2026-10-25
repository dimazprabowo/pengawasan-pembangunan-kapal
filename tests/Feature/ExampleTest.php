<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
