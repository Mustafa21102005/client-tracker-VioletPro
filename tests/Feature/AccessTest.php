<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessTest extends TestCase
{
    #[DataProvider('protectedPages')]
    public function test_guests_are_sent_to_the_login_page(string $url): void
    {
        $this->get($url)->assertRedirect(route('login'));
    }

    public static function protectedPages(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'clients' => ['/clients'],
            'edits' => ['/edits'],
            'attachments' => ['/attachments'],
            'reports' => ['/reports'],
            'report export' => ['/reports/export'],
            'client search' => ['/clients/search'],
            'company search' => ['/companies/search'],
            'project search' => ['/projects/search'],
            'edit search' => ['/edits/search'],
        ];
    }

    public function test_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'Intruder', 'email' => 'intruder@example.com', 'password' => 'password'])->assertNotFound();
    }

    public function test_the_delete_account_route_no_longer_exists(): void
    {
        $this->signIn();

        $this->delete('/settings/profile')->assertStatus(405);
    }
}
