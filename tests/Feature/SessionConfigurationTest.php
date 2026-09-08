<?php

namespace Tests\Feature;

use Tests\TestCase;

class SessionConfigurationTest extends TestCase
{
    public function test_session_cookie_uses_a_stable_ascii_default(): void
    {
        $this->assertSame('imgleomessi_session', config('session.cookie'));
    }
}