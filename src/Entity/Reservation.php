<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_reservation'])]
    private ?int $id = null;

    #[Groups(['api_reservation'])]
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[Groups(['api_reservation'])]
    #[ORM\Column(length: 50)]
    private ?string $service = null;

    #[Groups(['api_reservation'])]
    #[ORM\Column]
    private ?int $np_people = null;

    #[Groups(['api_reservation'])]
    #[ORM\ManyToOne(inversedBy: 'reservations')]
    private ?User $user = null;

    /**
     * @var Collection<int, Table>
     */
    #[Groups(['api_reservation'])]
    #[ORM\ManyToMany(targetEntity: Table::class, inversedBy: 'reservations')]
    private Collection $tables;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    public function __construct()
    {
        $this->tables = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getService(): ?string
    {
        return $this->service;
    }

    public function setService(string $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getNpPeople(): ?int
    {
        return $this->np_people;
    }

    public function setNpPeople(int $np_people): static
    {
        $this->np_people = $np_people;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, Table>
     */
    public function getTables(): Collection
    {
        return $this->tables;
    }

    public function addTable(Table $table): static
    {
        if (!$this->tables->contains($table)) {
            $this->tables->add($table);
        }

        return $this;
    }

    public function removeTable(Table $table): static
    {
        $this->tables->removeElement($table);

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
}
