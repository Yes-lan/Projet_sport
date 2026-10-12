<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class WorkoutSet
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne(inversedBy: 'sets')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private WorkoutSession $session;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Exercise $exercise;
    #[ORM\Column]
    private int $setNumber = 1;
    #[ORM\Column]
    private float $weight = 0;
    #[ORM\Column]
    private int $reps = 0;
    #[ORM\Column(nullable: true)]
    private ?int $rpe = null;
    #[ORM\Column]
    private bool $completed = false;

    public function __construct(Exercise $exercise, float $weight, int $reps) { $this->exercise = $exercise; $this->weight = $weight; $this->reps = $reps; }
    public function setSession(WorkoutSession $session): void { $this->session = $session; }
    public function getId(): ?int { return $this->id; }
    public function getExercise(): Exercise { return $this->exercise; }
    public function getSetNumber(): int { return $this->setNumber; }
    public function setSetNumber(int $number): self { $this->setNumber = $number; return $this; }
    public function getWeight(): float { return $this->weight; }
    public function getReps(): int { return $this->reps; }
    public function getRpe(): ?int { return $this->rpe; }
    public function setRpe(?int $rpe): self { $this->rpe = $rpe; return $this; }
    public function isCompleted(): bool { return $this->completed; }
    public function setCompleted(bool $completed): self { $this->completed = $completed; return $this; }
}
