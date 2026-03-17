<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="username"]');
        $this->assertSelectorExists('input[name="password"]');
    }

    public function testRegisterPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    public function testRegisterWithMismatchedPasswords(): void
    {
        $client = static::createClient();
        $client->request('POST', '/register', [
            'username' => 'testuser_' . uniqid(),
            'password' => 'Password123!',
            'password_confirm' => 'Different456!',
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.alert-error', 'Passwords do not match');
    }

    public function testProtectedReservationRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/events/1/reserve');
        // Should redirect to login
        $this->assertResponseRedirects('/login');
    }
}