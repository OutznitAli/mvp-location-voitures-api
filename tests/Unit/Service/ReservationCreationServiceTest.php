<?php

namespace App\Tests\Unit\Service;

use App\Entity\Car;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Service\ReservationCreationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ReservationCreationServiceTest extends TestCase
{
    private ReservationRepository&MockObject $reservationRepository;
    private ReservationCreationService $service;

    protected function setUp(): void
    {
        $this->reservationRepository = $this->createMock(ReservationRepository::class);
        $this->service = new ReservationCreationService($this->reservationRepository);
    }

    // --- Date coherence ---

    public function testPrepareForCreationSucceedsWithValidDates(): void
    {
        $reservation = $this->makeReservation('2026-06-01', '2026-06-10');

        $this->reservationRepository->method('hasOverlapForCar')->willReturn(false);

        $user = new User();
        $this->service->prepareForCreation($reservation, $user);

        $this->assertSame(ReservationCreationService::STATUS_PENDING, $reservation->getStatus());
        $this->assertSame($user, $reservation->getReservations());
    }

    public function testPrepareForCreationRejectsEndDateBeforeStartDate(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('endDate cannot be earlier than startDate');

        $reservation = $this->makeReservation('2026-06-10', '2026-06-01');
        $this->service->prepareForCreation($reservation, new User());
    }

    public function testPrepareForCreationAllowsSameDayStartAndEnd(): void
    {
        $reservation = $this->makeReservation('2026-06-01', '2026-06-01');

        $this->reservationRepository->method('hasOverlapForCar')->willReturn(false);

        $this->service->prepareForCreation($reservation, new User());
        $this->assertSame(ReservationCreationService::STATUS_PENDING, $reservation->getStatus());
    }

    // --- Overlap guard ---

    public function testPrepareForCreationRejectsOverlappingBooking(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('already reserved');

        $reservation = $this->makeReservation('2026-06-05', '2026-06-08');

        $this->reservationRepository->method('hasOverlapForCar')->willReturn(true);

        $this->service->prepareForCreation($reservation, new User());
    }

    public function testPrepareForCreationPassesWhenNoOverlap(): void
    {
        $reservation = $this->makeReservation('2026-07-01', '2026-07-05');

        $this->reservationRepository->method('hasOverlapForCar')->willReturn(false);

        $this->service->prepareForCreation($reservation, new User());
        $this->assertSame(ReservationCreationService::STATUS_PENDING, $reservation->getStatus());
    }

    // --- prepareForUpdate ownership ---

    public function testPrepareForUpdateRejectsWhenNotOwner(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
        $this->expectExceptionMessage('own reservations');

        $owner = $this->makeUserWithId(1);
        $other = $this->makeUserWithId(2);

        $reservation = $this->makeReservation('2026-06-01', '2026-06-10');
        $reservation->setReservations($owner);

        $this->service->prepareForUpdate($reservation, $other);
    }

    public function testPrepareForUpdateSucceedsForOwner(): void
    {
        $owner = $this->makeUserWithId(1);
        $reservation = $this->makeReservation('2026-06-01', '2026-06-10');
        $reservation->setReservations($owner);

        $this->reservationRepository->method('hasOverlapForCar')->willReturn(false);

        $this->service->prepareForUpdate($reservation, $owner);
        $this->assertNotNull($reservation->getUpdatedAt());
    }

    // --- Helpers ---

    private function makeReservation(string $start, string $end): Reservation
    {
        $car = new Car();
        $reservation = new Reservation();
        $reservation->setStartDate(new \DateTime($start));
        $reservation->setEndDate(new \DateTime($end));
        $reservation->setCar($car);

        return $reservation;
    }

    private function makeUserWithId(int $id): User
    {
        $user = new User();
        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, $id);

        return $user;
    }
}
