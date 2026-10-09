<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_picture_is_used_on_more_than_one_page_header(): void
    {
        $all = collect(config('heroes'))->flatten();

        $this->assertSame($all->count(), $all->unique()->count());
        $all->each(fn ($file) => $this->assertFileExists(public_path('images/archive/'.$file)));
    }

    public function test_page_headers_show_their_own_carousel(): void
    {
        $this->get('/')->assertOk()->assertSee('x-data="carousel(6)"', false)->assertSee('images/archive/godram-13.webp', false);
        $this->get('/login')->assertOk()->assertSee('images/archive/godram-24.webp', false)->assertDontSee('images/archive/godram-13.webp', false);
    }
}
