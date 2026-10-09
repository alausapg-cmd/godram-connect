<?php

namespace Tests\Feature;

use Tests\Concerns\BuildsMinistry;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use BuildsMinistry;

    public function test_public_pages_load_for_guests(): void
    {
        $this->buildMinistry();

        foreach (['home', 'about', 'archive', 'highlights', 'watch', 'events', 'academy', 'announcements.index', 'network', 'login', 'register', 'offline'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('network', $this->lagos))->assertOk()->assertSee('Agege');
    }

    public function test_private_pages_send_guests_to_sign_in(): void
    {
        foreach (['dashboard', 'members.index', 'reports.index', 'transfers.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_install_files_are_served(): void
    {
        $this->assertFileExists(public_path('manifest.webmanifest'));
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('icons/icon-512.png'));
        $this->assertJson(file_get_contents(public_path('manifest.webmanifest')));
    }

    public function test_an_unknown_address_shows_the_not_found_page(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('We could not find that page');
    }
}
