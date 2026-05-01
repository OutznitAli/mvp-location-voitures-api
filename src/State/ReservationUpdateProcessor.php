<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Service\ReservationCreationService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<Reservation, Reservation>
 */
final readonly class ReservationUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private ReservationCreationService $reservationCreationService,
        private Security $security,
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
    ) {
    }

    /**
     * @param Reservation $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Reservation
    {
        if (!$data instanceof Reservation) {
            throw new \InvalidArgumentException('Expected Reservation data in ReservationUpdateProcessor.');
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('You must be authenticated to update a reservation.');
        }

        // Fetch the existing reservation by ID from URI for ownership check
        $existingId = (int) ($uriVariables['id'] ?? 0);
        if ($existingId === 0) {
            throw new NotFoundHttpException('Reservation not found.');
        }

        $existing = $this->reservationRepository->find($existingId);
        if (!$existing instanceof Reservation) {
            throw new NotFoundHttpException('Reservation not found.');
        }

        // Ownership check and business validation on existing entity
        if ($existing->getReservations()?->getId() !== $user->getId()) {
            throw new AccessDeniedHttpException('You can only update your own reservations.');
        }

        // Apply incoming data to existing entity
        $existing->setStartDate($data->getStartDate() ?? $existing->getStartDate());
        $existing->setEndDate($data->getEndDate() ?? $existing->getEndDate());
        $existing->setCar($data->getCar() ?? $existing->getCar());

        // Re-validate business rules with the merged entity
        $this->reservationCreationService->prepareForUpdate($existing, $user);

        /** @var Reservation $reservation */
        $reservation = $this->persistProcessor->process($existing, $operation, $uriVariables, $context);

        return $reservation;
    }
}
