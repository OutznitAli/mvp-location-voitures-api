<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Car;
use App\Repository\CarRepository;

/**
 * @implements ProviderInterface<Car|null>
 */
final readonly class CarItemProvider implements ProviderInterface
{
    public function __construct(private CarRepository $carRepository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?Car
    {
        $rawId = $uriVariables['id'] ?? null;

        if (!is_scalar($rawId) || !ctype_digit((string) $rawId)) {
            return null;
        }

        return $this->carRepository->findAvailableCarById((int) $rawId);
    }
}
