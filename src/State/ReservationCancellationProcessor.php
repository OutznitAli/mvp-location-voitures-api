<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Cancels a reservation by switching its status to 'cancelled' instead of deleting the record.
 * This preserves audit history while honouring the DELETE HTTP verb contract.
 *
 * @implements ProcessorInterface<Reservation, void>
 */
final readonly class ReservationCancellationProcessor implements ProcessorInterface
{
    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param Reservation $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof Reservation) {
            throw new \InvalidArgumentException('Expected Reservation data in ReservationCancellationProcessor.');
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('You must be authenticated to cancel a reservation.');
        }

        if ($data->getReservations()?->getId() !== $user->getId()) {
            throw new AccessDeniedHttpException('You can only cancel your own reservations.');
        }

        $data->setStatus(self::STATUS_CANCELLED);
        $data->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }
}
