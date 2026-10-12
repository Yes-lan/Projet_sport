<?php

namespace App\Controller;

use App\Entity\Exercise;
use App\Entity\Measurement;
use App\Entity\PersonalRecord;
use App\Entity\User;
use App\Entity\WorkoutSession;
use App\Entity\WorkoutSet;
use App\Entity\WorkoutRoutine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Bundle\SecurityBundle\Security;

#[Route('/api')]
final class ApiController
{
    #[Route('/register', methods: ['POST'])]
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $hasher): JsonResponse
    {
        $data = $this->json($request);
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            return new JsonResponse(['error' => 'Un email valide et un mot de passe de 8 caractères sont requis.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($entityManager->getRepository(User::class)->findOneBy(['email' => $email])) {
            return new JsonResponse(['error' => 'Cet email est déjà utilisé.'], Response::HTTP_CONFLICT);
        }
        $user = (new User())->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, $password));
        $entityManager->persist($user);
        $entityManager->flush();
        return new JsonResponse(['id' => $user->getId(), 'email' => $user->getEmail()], Response::HTTP_CREATED);
    }

    #[Route('/login', methods: ['POST'])]
    public function login(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $hasher, Security $security): JsonResponse
    {
        $data = $this->json($request);
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => strtolower(trim((string) ($data['username'] ?? $data['email'] ?? '')))]);
        if (!$user || !$hasher->isPasswordValid($user, (string) ($data['password'] ?? ''))) {
            return new JsonResponse(['error' => 'Identifiants invalides.'], Response::HTTP_UNAUTHORIZED);
        }
        $security->login($user);
        return new JsonResponse(['id' => $user->getId(), 'email' => $user->getEmail(), 'roles' => $user->getRoles()]);
    }

    #[Route('/login-check', name: 'api_login_check', methods: ['POST'])]
    public function loginCheck(): JsonResponse
    {
        return new JsonResponse(['error' => 'Identifiants invalides.'], Response::HTTP_UNAUTHORIZED);
    }

    #[Route('/me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse(['id' => $user->getId(), 'email' => $user->getEmail(), 'roles' => $user->getRoles()]);
    }

    #[Route('/exercises', methods: ['GET', 'POST'])]
    public function exercises(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($request->isMethod('POST')) {
            $data = $this->json($request);
            $name = trim((string) ($data['name'] ?? ''));
            $group = trim((string) ($data['muscleGroup'] ?? ''));
            if ($name === '' || $group === '') {
                return new JsonResponse(['error' => 'Le nom et le groupe musculaire sont requis.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $exercise = (new Exercise())->setName($name)->setMuscleGroup($group);
            $entityManager->persist($exercise);
            $entityManager->flush();
            return new JsonResponse(['id' => $exercise->getId(), 'name' => $exercise->getName(), 'muscleGroup' => $exercise->getMuscleGroup()], Response::HTTP_CREATED);
        }
        $items = $entityManager->getRepository(Exercise::class)->findBy([], ['name' => 'ASC']);
        return new JsonResponse(array_map(static fn (Exercise $item): array => ['id' => $item->getId(), 'name' => $item->getName(), 'muscleGroup' => $item->getMuscleGroup()], $items), $request->isMethod('POST') ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    #[Route('/sessions', methods: ['GET', 'POST'])]
    public function sessions(Request $request, #[CurrentUser] User $user, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($request->isMethod('POST')) {
            $data = $this->json($request);
            $name = trim((string) ($data['name'] ?? 'Séance'));
            $session = new WorkoutSession($user, $name);
            $session->setEnergyRating(isset($data['energyRating']) ? (int) $data['energyRating'] : null);
            foreach (($data['sets'] ?? []) as $index => $setData) {
                $exercise = $entityManager->getRepository(Exercise::class)->find((int) ($setData['exerciseId'] ?? 0));
                if (!$exercise || (int) ($setData['reps'] ?? 0) < 1 || (float) ($setData['weight'] ?? 0) < 0) {
                    return new JsonResponse(['error' => 'Chaque série doit contenir un exercice, un poids et des répétitions valides.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $set = (new WorkoutSet($exercise, (float) $setData['weight'], (int) $setData['reps']))->setSetNumber($index + 1)->setRpe(isset($setData['rpe']) ? (int) $setData['rpe'] : null)->setCompleted((bool) ($setData['completed'] ?? false));
                $session->addSet($set);
            }
            $session->finish();
            $entityManager->persist($session);
            $entityManager->flush();
        }
        $sessions = $entityManager->getRepository(WorkoutSession::class)->findBy(['user' => $user], ['startedAt' => 'DESC']);
        return new JsonResponse(array_map(static function (WorkoutSession $item): array {
            $volume = 0;
            foreach ($item->getSets() as $set) {
                if ($set->isCompleted()) {
                    $volume += $set->getWeight() * $set->getReps();
                }
            }
            return ['id' => $item->getId(), 'name' => $item->getName(), 'startedAt' => $item->getStartedAt()->format(DATE_ATOM), 'finishedAt' => $item->getFinishedAt()?->format(DATE_ATOM), 'energyRating' => $item->getEnergyRating(), 'sets' => $item->getSets()->count(), 'volume' => $volume];
        }, $sessions), $request->isMethod('POST') ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    #[Route('/routines', methods: ['GET', 'POST'])]
    public function routines(Request $request, #[CurrentUser] User $user, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($request->isMethod('POST')) {
            $data = $this->json($request);
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                return new JsonResponse(['error' => 'Le nom de la routine est obligatoire.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $routine = new WorkoutRoutine($user, $name, $data['exerciseIds'] ?? []);
            $entityManager->persist($routine);
            $entityManager->flush();
            return new JsonResponse(['id' => $routine->getId(), 'name' => $routine->getName(), 'exerciseIds' => $routine->getExerciseIds()], Response::HTTP_CREATED);
        }
        $routines = $entityManager->getRepository(WorkoutRoutine::class)->findBy(['user' => $user], ['name' => 'ASC']);
        return new JsonResponse(array_map(static fn (WorkoutRoutine $routine): array => ['id' => $routine->getId(), 'name' => $routine->getName(), 'exerciseIds' => $routine->getExerciseIds()], $routines));
    }

    #[Route('/routines/{id}', methods: ['DELETE'])]
    public function deleteRoutine(int $id, #[CurrentUser] User $user, EntityManagerInterface $entityManager): JsonResponse
    {
        $routine = $entityManager->getRepository(WorkoutRoutine::class)->findOneBy(['id' => $id, 'user' => $user]);
        if (!$routine) {
            return new JsonResponse(['error' => 'Routine introuvable.'], Response::HTTP_NOT_FOUND);
        }
        $entityManager->remove($routine);
        $entityManager->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/measurements', methods: ['GET', 'POST'])]
    public function measurements(Request $request, #[CurrentUser] User $user, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($request->isMethod('POST')) {
            $data = $this->json($request);
            $measurement = (new Measurement($user))->setWeight($this->number($data['weight'] ?? null))->setArm($this->number($data['arm'] ?? null))->setChest($this->number($data['chest'] ?? null))->setWaist($this->number($data['waist'] ?? null))->setThigh($this->number($data['thigh'] ?? null));
            $entityManager->persist($measurement);
            $entityManager->flush();
        }
        $items = $entityManager->getRepository(Measurement::class)->findBy(['user' => $user], ['measuredAt' => 'DESC']);
        return new JsonResponse(array_map(static fn (Measurement $item): array => $item->toArray(), $items), $request->isMethod('POST') ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    #[Route('/records', methods: ['GET', 'POST'])]
    public function records(Request $request, #[CurrentUser] User $user, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($request->isMethod('POST')) {
            $data = $this->json($request);
            $name = trim((string) ($data['name'] ?? ''));
            $weight = (float) ($data['weight'] ?? 0);
            $reps = (int) ($data['reps'] ?? 0);
            if ($name === '' || $weight <= 0 || $reps < 1) {
                return new JsonResponse(['error' => 'Le mouvement, la charge et les répétitions sont obligatoires.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $record = new PersonalRecord($user, $name, $weight, $reps);
            $entityManager->persist($record);
            $entityManager->flush();
            return new JsonResponse($record->toArray(), Response::HTTP_CREATED);
        }
        $records = $entityManager->getRepository(PersonalRecord::class)->findBy(['user' => $user], ['recordedAt' => 'DESC']);
        return new JsonResponse(array_map(static fn (PersonalRecord $record): array => $record->toArray(), $records));
    }

    private function json(Request $request): array
    {
        $data = json_decode($request->getContent(), true);
        return is_array($data) ? $data : [];
    }

    private function number(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : max(0, (float) $value);
    }
}
