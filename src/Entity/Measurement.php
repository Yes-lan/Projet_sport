<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Measurement
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;
    #[ORM\Column]
    private \DateTimeImmutable $measuredAt;
    #[ORM\Column(nullable: true)]
    private ?float $weight = null;
    #[ORM\Column(nullable: true)]
    private ?float $arm = null;
    #[ORM\Column(nullable: true)]
    private ?float $chest = null;
    #[ORM\Column(nullable: true)]
    private ?float $waist = null;
    #[ORM\Column(nullable: true)]
    private ?float $thigh = null;

    public function __construct(User $user) { $this->user = $user; $this->measuredAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getMeasuredAt(): \DateTimeImmutable { return $this->measuredAt; }
    public function setWeight(?float $value): self { $this->weight = $value; return $this; }
    public function setArm(?float $value): self { $this->arm = $value; return $this; }
    public function setChest(?float $value): self { $this->chest = $value; return $this; }
    public function setWaist(?float $value): self { $this->waist = $value; return $this; }
    public function setThigh(?float $value): self { $this->thigh = $value; return $this; }
    public function toArray(): array { return ['id' => $this->id, 'date' => $this->measuredAt->format('Y-m-d'), 'weight' => $this->weight, 'arm' => $this->arm, 'chest' => $this->chest, 'waist' => $this->waist, 'thigh' => $this->thigh]; }
}
