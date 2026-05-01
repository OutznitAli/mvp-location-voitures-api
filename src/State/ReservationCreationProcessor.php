<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Reservation;
use App\Entity\User;
use App\Service\ReservationCreationService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<Reservation, Reservation>
 */
final readonly class ReservationCreationProcessor implements ProcessorInterface
{
    public function __construct(
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
            throw new \InvalidArgumentException('Expected Reservation data in ReservationCreationProcessor.');
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('You must be authenticated to create a reservation.');
        }

        $this->reservationCreationService->prepareForCreation($data, $user);

        /** @var Reservation $reservation */
        $reservation = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        return $reservation;
    }
}
