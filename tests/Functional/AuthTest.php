<?php

namespace App\Tests\Functional;

class AuthTest extends ApiTestCase
{
    public function testLoginReturnsToken(): void
    {
        $this->createUser('test@example.com', 'password123');

        $this->client->request('POST', '/api/login_check', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]));

        $this->assertResponseIsSuccessful();
        $data = $this->responseData();
        $this->assertArrayHasKey('token', $data);
        $this->assertNotEmpty($data['token']);
    }

    public function testLoginWithWrongPasswordReturns401(): void
    {
        $this->createUser('test@example.com', 'password123');

        $this->client->request('POST', '/api/login_check', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testProtectedRouteReturns401WithoutToken(): void
    {
        $this->jsonRequest('GET', '/api/cars');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testProtectedRouteSucceedsWithValidToken(): void
    {
        $this->createUser('test@example.com', 'password123');
        $token = $this->getToken('test@example.com', 'password123');

        $this->jsonRequest('GET', '/api/cars', null, $token);

        $this->assertResponseIsSuccessful();
    }
}
