<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventControllerTest extends WebTestCase
{
    public function testEventsIndexLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/events');
        $this->assertResponseIsSuccessful();
    }

    public function testAdminDashboardRequiresAdminRole(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');
        // Should redirect to admin login
        $this->assertResponseRedirects('/admin/login');
    }

    public function testAdminLoginPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/login');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="username"]');
    }

    public function testApiLoginReturns401WithBadCredentials(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['username' => 'nobody', 'password' => 'wrongpass']));
        $this->assertResponseStatusCodeSame(401);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
    }
}