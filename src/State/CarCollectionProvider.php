<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Car;
use App\Repository\CarRepository;

/**
 * @implements ProviderInterface<list<Car>>
 */
final readonly class CarCollectionProvider implements ProviderInterface
{
    public function __construct(private CarRepository $carRepository)
    {
    }

    /**
     * @return list<Car>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->carRepository->findAvailableCars();
    }
}
