<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PersonalRecord
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;
    #[ORM\Column(length: 120)]
    private string $name = '';
    #[ORM\Column]
    private float $weight = 0;
    #[ORM\Column]
    private int $reps = 1;
    #[ORM\Column]
    private \DateTimeImmutable $recordedAt;

    public function __construct(User $user, string $name, float $weight, int $reps)
    {
        $this->user = $user;
        $this->name = trim($name);
        $this->weight = $weight;
        $this->reps = $reps;
        $this->recordedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getWeight(): float { return $this->weight; }
    public function getReps(): int { return $this->reps; }
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'weight' => $this->weight,
            'reps' => $this->reps,
            'date' => $this->recordedAt->format('d/m/Y'),
        ];
    }
}
