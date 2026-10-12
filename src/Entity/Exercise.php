<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Exercise
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 120)]
    private string $name = '';
    #[ORM\Column(length: 80)]
    private string $muscleGroup = '';

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = trim($name); return $this; }
    public function getMuscleGroup(): string { return $this->muscleGroup; }
    public function setMuscleGroup(string $group): self { $this->muscleGroup = trim($group); return $this; }
}
