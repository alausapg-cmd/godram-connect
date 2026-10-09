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
        $all->each(fn ($file) => $this->assertFileExists(public_path('images/'.$file)));
    }

    public function test_every_header_picture_has_a_caption(): void
    {
        $known = collect(config('archive'))->map(fn ($a) => 'archive/'.$a['file'])
            ->merge(collect(config('gallery'))->map(fn ($g) => 'gallery/'.$g['file']));

        collect(config('heroes'))->flatten()->each(fn ($path) => $this->assertContains($path, $known));
    }

    public function test_page_headers_show_their_own_carousel(): void
    {
        $this->get('/')->assertOk()->assertSee('x-data="carousel(16)"', false)->assertSee('images/gallery/convention-2026-5878.webp', false);
        $this->get('/login')->assertOk()->assertSee('images/gallery/convention-2026-5914.webp', false)->assertDontSee('images/gallery/convention-2026-5878.webp', false);
    }
}
