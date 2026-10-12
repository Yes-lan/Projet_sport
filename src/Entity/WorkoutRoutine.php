<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class WorkoutRoutine
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(type: 'json')]
    private array $exerciseIds = [];

    public function __construct(User $user, string $name, array $exerciseIds = [])
    {
        $this->user = $user;
        $this->name = trim($name);
        $this->exerciseIds = array_values(array_map('intval', $exerciseIds));
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getExerciseIds(): array { return $this->exerciseIds; }
    public function setExerciseIds(array $exerciseIds): self { $this->exerciseIds = array_values(array_map('intval', $exerciseIds)); return $this; }
}
