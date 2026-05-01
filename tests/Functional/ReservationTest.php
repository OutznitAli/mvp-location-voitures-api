<?php

namespace App\Tests\Functional;

class ReservationTest extends ApiTestCase
{
    // --- POST /api/reservations ---

    public function testCreateReservationReturns201(): void
    {
        $user = $this->createUser('user@example.com', 'pass1234');
        $car = $this->createCar();
        $token = $this->getToken('user@example.com', 'pass1234');

        $this->jsonRequest('POST', '/api/reservations', [
            'startDate' => '2026-07-01',
            'endDate'   => '2026-07-05',
            'car'       => '/api/cars/' . $car->getId(),
        ], $token);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->responseData();
        $this->assertSame('pending', $data['status']);
        $this->assertNotEmpty($data['id']);
    }

    public function testCreateReservationRejects401WithoutToken(): void
    {
        $car = $this->createCar();

        $this->jsonRequest('POST', '/api/reservations', [
            'startDate' => '2026-07-01',
            'endDate'   => '2026-07-05',
            'car'       => '/api/cars/' . $car->getId(),
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateReservationRejectsInvalidDateRange(): void
    {
        $user = $this->createUser('user@example.com', 'pass1234');
        $car = $this->createCar();
        $token = $this->getToken('user@example.com', 'pass1234');

        $this->jsonRequest('POST', '/api/reservations', [
            'startDate' => '2026-07-10',
            'endDate'   => '2026-07-01',
            'car'       => '/api/cars/' . $car->getId(),
        ], $token);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateReservationRejectsOverlapConflict(): void
    {
        $user = $this->createUser('user@example.com', 'pass1234');
        $car = $this->createCar();
        $token = $this->getToken('user@example.com', 'pass1234');

        $this->jsonRequest('POST', '/api/reservations', [
            'startDate' => '2026-08-01',
            'endDate'   => '2026-08-10',
            'car'       => '/api/cars/' . $car->getId(),
        ], $token);

        $this->assertResponseStatusCodeSame(201);

        $this->jsonRequest('POST', '/api/reservations', [
            'startDate' => '2026-08-05',
            'endDate'   => '2026-08-12',
            'car'       => '/api/cars/' . $car->getId(),
        ], $token);

        $this->assertResponseStatusCodeSame(422);
    }

    // --- GET /api/users/{id}/reservations ---

    public function testGetOwnReservationsReturnsCollection(): void
    {
        $user = $this->createUser('user@example.com', 'pass1234');
        $car = $this->createCar();
        $token = $this->getToken('user@example.com', 'pass1234');

        $this->jsonRequest('POST', '/api/reservations', [
            'startDate' => '2026-09-01',
            'endDate'   => '2026-09-05',
            'car'       => '/api/cars/' . $car->getId(),
        ], $token);

        $this->jsonRequest('GET', '/api/users/' . $user->getId() . '/reservations', null, $token);

        $this->assertResponseIsSuccessful();
        $data = $this->responseData();
        $this->assertIsArray($data['member']);
        $this->assertCount(1, $data['member']);
    }

    public function testGetOtherUserReservationsReturns403(): void
    {
        $userA = $this->createUser('a@example.com', 'pass1234');
        $userB = $this->createUser('b@example.com', 'pass1234');
        $tokenB = $this->getToken('b@example.com', 'pass1234');

        $this->jsonRequest('GET', '/api/users/' . $userA->getId() . '/reservations', null, $tokenB);

        $this->assertResponseStatusCodeSame(403);
    }

    // --- PUT /api/reservations/{id} ---

    public function testUpdateOwnReservationReturns200(): void
    {
        $user = $this->createUser('user@example.com', 'pass1234');
        $car = $this->createCar();
        $token = $this->getToken('user@example.com', 'pass1234');

        $this->jsonRequest('POST', '/api/reservations', [
            'startDate' => '2026-10-01',
            'endDate'   => '2026-10-05',
            'car'       => '/api/cars/' . $car->getId(),
        ], $token);

        $iri = $this->responseData()['@id'];
        $id = basename($iri);

        $this->jsonRequest('PUT', '/api/reservations/' . $id, [
            'startDate' => '2026-10-02',
            'endDate'   => '2026-10-06',
            'car'       => '/api/cars/' . $car->getId(),
        ], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->responseData();
        $this->assertStringStartsWith('2026-10-02', $data['startDate']);
    }

    public function testUpdateOtherUserReservationReturns403(): void
    {
        $userA = $this->createUser('a@example.com', 'pass1234');
        $userB = $this->createUser('b@example.com', 'pass1234');
        $car = $this->createCar();
        $tokenA = $this->getToken('a@example.com', 'pass1234');
        $tokenB = $this->getToken('b@example.com', 'pass1234');

        $this->jsonRequest('POST', '/api/reservations', [
            'startDate' => '2026-11-01',
            'endDate'   => '2026-11-05',
            'car'       => '/api/cars/' . $car->getId(),
        ], $tokenA);

        $id = basename($this->responseData()['@id']);

        $this->jsonRequest('PUT', '/api/reservations/' . $id, [
            'startDate' => '2026-11-02',
            'endDate'   => '2026-11-06',
            'car'       => '/api/cars/' . $car->getId(),
        ], $tokenB);

        $this->assertResponseStatusCodeSame(403);
    }

    // --- DELETE /api/reservations/{id} ---

    public function testCancelOwnReservationReturns204(): void
    {
        $user = $this->createUser('user@example.com', 'pass1234');
        $car = $this->createCar();
        $token = $this->getToken('user@example.com', 'pass1234');

        $this->jsonRequest('POST', '/api/reservations', [
            'startDate' => '2026-12-01',
            'endDate'   => '2026-12-05',
            'car'       => '/api/cars/' . $car->getId(),
        ], $token);

        $id = basename($this->responseData()['@id']);

        $this->jsonRequest('DELETE', '/api/reservations/' . $id, null, $token);

        $this->assertResponseStatusCodeSame(204);
    }

    public function testCancelOtherUserReservationReturns403(): void
    {
        $userA = $this->createUser('a@example.com', 'pass1234');
        $userB = $this->createUser('b@example.com', 'pass1234');
        $car = $this->createCar();
        $tokenA = $this->getToken('a@example.com', 'pass1234');
        $tokenB = $this->getToken('b@example.com', 'pass1234');

        $this->jsonRequest('POST', '/api/reservations', [
            'startDate' => '2026-12-10',
            'endDate'   => '2026-12-15',
            'car'       => '/api/cars/' . $car->getId(),
        ], $tokenA);

        $id = basename($this->responseData()['@id']);

        $this->jsonRequest('DELETE', '/api/reservations/' . $id, null, $tokenB);

        $this->assertResponseStatusCodeSame(403);
    }
}
