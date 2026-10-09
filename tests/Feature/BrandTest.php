<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_official_logo_files_exist(): void
    {
        $this->assertFileExists(public_path(config('godram.logo.svg')));
        $this->assertFileExists(public_path(config('godram.logo.png')));
    }

    public function test_pages_show_the_godram_logo(): void
    {
        $this->get('/')->assertOk()->assertSee(config('godram.logo.svg'));
        $this->get('/login')->assertOk()->assertSee(config('godram.logo.svg'));
    }
}
