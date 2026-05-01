<?php

namespace App\Service;

use App\Entity\Car;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ReservationCreationService
{
    public const STATUS_PENDING = 'pending';

    public function __construct(
        private readonly ReservationRepository $reservationRepository,
    ) {
    }

    public function prepareForCreation(Reservation $reservation, User $user): void
    {
        $this->assertDateCoherence($reservation);
        $this->assertNoOverlapConflict($reservation);

        if ($reservation->getStatus() === null || trim($reservation->getStatus()) === '') {
            $reservation->setStatus(self::STATUS_PENDING);
        }

        $reservation->setReservations($user);
        $reservation->setUpdatedAt(new \DateTimeImmutable());
    }

    public function prepareForUpdate(Reservation $reservation, User $currentUser): void
    {
        if ($reservation->getReservations()?->getId() !== $currentUser->getId()) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('You can only update your own reservations.');
        }

        $this->assertDateCoherence($reservation);
        $this->assertNoOverlapConflict($reservation, $reservation->getId());

        $reservation->setUpdatedAt(new \DateTimeImmutable());
    }

    private function assertDateCoherence(Reservation $reservation): void
    {
        $startDate = $reservation->getStartDate();
        $endDate = $reservation->getEndDate();

        if ($startDate === null || $endDate === null) {
            return;
        }

        if ($endDate < $startDate) {
            throw new UnprocessableEntityHttpException('The endDate cannot be earlier than startDate.');
        }
    }

    private function assertNoOverlapConflict(Reservation $reservation, ?int $excludeReservationId = null): void
    {
        $car = $reservation->getCar();
        $startDate = $reservation->getStartDate();
        $endDate = $reservation->getEndDate();

        if (!$car instanceof Car || $startDate === null || $endDate === null) {
            return;
        }

        if ($this->reservationRepository->hasOverlapForCar($car, $startDate, $endDate, $excludeReservationId)) {
            throw new UnprocessableEntityHttpException('This car is already reserved for the requested date range.');
        }
    }
}
