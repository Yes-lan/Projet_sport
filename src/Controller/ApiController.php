<?php

namespace App\Controller;

use App\Entity\Exercise;
use App\Entity\Measurement;
use App\Entity\User;
use App\Entity\WorkoutSession;
use App\Entity\WorkoutSet;
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
        return new JsonResponse(array_map(static fn (WorkoutSession $item): array => ['id' => $item->getId(), 'name' => $item->getName(), 'startedAt' => $item->getStartedAt()->format(DATE_ATOM), 'finishedAt' => $item->getFinishedAt()?->format(DATE_ATOM), 'energyRating' => $item->getEnergyRating(), 'sets' => $item->getSets()->count()], $sessions), $request->isMethod('POST') ? Response::HTTP_CREATED : Response::HTTP_OK);
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
