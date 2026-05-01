<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<Reservation>
 */
final readonly class UserReservationsProvider implements ProviderInterface
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $requestedId = (int) ($uriVariables['id'] ?? 0);

        $currentUser = $this->security->getUser();

        if (!$currentUser instanceof User) {
            throw new AccessDeniedHttpException('You must be authenticated to view reservations.');
        }

        if ($currentUser->getId() !== $requestedId) {
            throw new AccessDeniedHttpException('You can only view your own reservations.');
        }

        return $this->reservationRepository->findByUser($currentUser);
    }
}
