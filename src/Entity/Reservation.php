<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\ReservationRepository;
use App\State\ReservationCreationProcessor;
use App\State\UserReservationsProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/reservations',
            processor: ReservationCreationProcessor::class,
            denormalizationContext: ['groups' => ['reservation:write']],
            normalizationContext: ['groups' => ['reservation:read']]
        ),
        new GetCollection(
            uriTemplate: '/users/{id}/reservations',
            requirements: ['id' => '\\d+'],
            provider: UserReservationsProvider::class,
            normalizationContext: ['groups' => ['reservation:read']]
        ),
    ]
)]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['reservation:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(groups: ['reservation:write'])]
    #[Groups(['reservation:read', 'reservation:write'])]
    private ?\DateTime $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(groups: ['reservation:write'])]
    #[Groups(['reservation:read', 'reservation:write'])]
    private ?\DateTime $endDate = null;

    #[ORM\Column(length: 255)]
    #[Groups(['reservation:read', 'reservation:write'])]
    private ?string $status = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[Groups(['reservation:read'])]
    private ?User $reservations = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[Assert\NotNull(groups: ['reservation:write'])]
    #[Groups(['reservation:read', 'reservation:write'])]
    private ?Car $car = null;

    #[ORM\Column]
    #[Groups(['reservation:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['reservation:read'])]
    private ?\DateTimeImmutable $updatedAt = null;


    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }
    

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTime $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTime $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getReservations(): ?User
    {
        return $this->reservations;
    }

    public function setReservations(?User $reservations): static
    {
        $this->reservations = $reservations;

        return $this;
    }

    public function getCar(): ?Car
    {
        return $this->car;
    }

    public function setCar(?Car $car): static
    {
        $this->car = $car;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[Assert\Callback]
    public function validateDateRange(ExecutionContextInterface $context): void
    {
        if ($this->startDate === null || $this->endDate === null) {
            return;
        }

        if ($this->endDate < $this->startDate) {
            $context->buildViolation('The endDate cannot be earlier than startDate.')
                ->atPath('endDate')
                ->addViolation();
        }
    }
}
