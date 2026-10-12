<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class WorkoutSession
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;
    #[ORM\Column(length: 120)]
    private string $name = '';
    #[ORM\Column]
    private \DateTimeImmutable $startedAt;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;
    #[ORM\Column(nullable: true)]
    private ?int $energyRating = null;
    #[ORM\OneToMany(mappedBy: 'session', targetEntity: WorkoutSet::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $sets;

    public function __construct(User $user, string $name) { $this->user = $user; $this->name = $name; $this->startedAt = new \DateTimeImmutable(); $this->sets = new ArrayCollection(); }
    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getName(): string { return $this->name; }
    public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }
    public function getFinishedAt(): ?\DateTimeImmutable { return $this->finishedAt; }
    public function finish(): self { $this->finishedAt = new \DateTimeImmutable(); return $this; }
    public function getEnergyRating(): ?int { return $this->energyRating; }
    public function setEnergyRating(?int $rating): self { $this->energyRating = $rating; return $this; }
    public function addSet(WorkoutSet $set): self { if (!$this->sets->contains($set)) { $this->sets->add($set); $set->setSession($this); } return $this; }
    public function getSets(): Collection { return $this->sets; }
}
