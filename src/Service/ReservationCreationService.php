<?php

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ReservationCreationService
{
    public const STATUS_PENDING = 'pending';

    public function prepareForCreation(Reservation $reservation, User $user): void
    {
        $this->assertDateCoherence($reservation);

        if ($reservation->getStatus() === null || trim($reservation->getStatus()) === '') {
            $reservation->setStatus(self::STATUS_PENDING);
        }

        $reservation->setReservations($user);
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
}
