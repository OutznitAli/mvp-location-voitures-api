<?php

namespace App\Tests\Functional;

use App\Entity\Car;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    private function resetDatabase(): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement('DELETE FROM reservation');
        $connection->executeStatement('DELETE FROM "user"');
        $connection->executeStatement('DELETE FROM car');
    }

    protected function createUser(string $email, string $plainPassword, array $roles = []): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, $plainPassword));
        $user->setRoles($roles);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function createCar(string $brand = 'Toyota', string $model = 'Yaris', int $price = 50, bool $available = true): Car
    {
        $car = new Car();
        $car->setBrand($brand);
        $car->setModel($model);
        $car->setUnitPricePerDay($price);
        $car->setIsAvailable($available);

        $this->em->persist($car);
        $this->em->flush();

        return $car;
    }

    protected function getToken(string $email, string $password): string
    {
        $this->client->request('POST', '/api/login_check', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => $password,
        ]));

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('token', $data, 'Login did not return a token.');

        return $data['token'];
    }

    protected function jsonRequest(string $method, string $uri, mixed $body = null, ?string $token = null): void
    {
        $headers = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->client->request($method, $uri, [], [], $headers, $body !== null ? json_encode($body) : null);
    }

    protected function responseData(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true) ?? [];
    }
}
