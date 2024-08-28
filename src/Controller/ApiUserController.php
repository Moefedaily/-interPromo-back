<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class ApiUserController extends AbstractController
{
    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $user = new User();
        $user->setMail($data['mail'] );
        $user->setName($data['name'] );
        $user->setPassword($data['password'] );
        $user->setPhone($data['phone']);
        $user->setAdmin($data['isAdmin'] ?? false);
        $user->setRoles(['ROLE_USER']);

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['message' => 'Validation failed', 'errors' => $errorMessages], JsonResponse::HTTP_BAD_REQUEST);
        }

        $hashedPassword = $passwordHasher->hashPassword($user, $user->getPassword());
        $user->setPassword($hashedPassword);

        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse([
            'message' => 'User registered successfully',
            'user' => [
                'id' => $user->getId(),
                'mail' => $user->getMail(),
                'name' => $user->getName(),
                'phone' => $user->getPhone(),
                'isAdmin' => $user->isAdmin()
            ]
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $mail = $data['mail'];
        $password = $data['password'];

        if (!$mail || !$password) {
            return new JsonResponse(['message' => 'Email and password are required.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user = $entityManager->getRepository(User::class)->findOneBy(['mail' => $mail]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return new JsonResponse(['message' => 'Invalid credentials.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->getId(),
                'mail' => $user->getMail(),
                'name' => $user->getName(),
                'phone' => $user->getPhone(),
                'isAdmin' => $user->isAdmin()
             ]
        ], JsonResponse::HTTP_OK);
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        return new JsonResponse(['message' => 'Logout successful'], JsonResponse::HTTP_OK);
    }

    #[Route('/users', name: 'api_users', methods: ['GET'])]
    public function users(EntityManagerInterface $entityManager): JsonResponse
    {
        $users = $entityManager->getRepository(User::class)->findAll();
        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'mail' => $user->getMail(),
                'name' => $user->getName(),
                'phone' => $user->getPhone(),
                'isAdmin' => $user->isAdmin()
            ];
        }
        return new JsonResponse($data, 200, [], true);
    }

    #[Route('/user/{id}', name: 'api_user', methods: ['GET'])]
    public function user(EntityManagerInterface $entityManager, int $id): JsonResponse
    {
        $user = $entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            return new JsonResponse(['message' => 'User not found'], JsonResponse::HTTP_NOT_FOUND);
        }
        $data = [
            'id' => $user->getId(),
            'mail' => $user->getMail(),
            'name' => $user->getName(),
            'phone' => $user->getPhone(),
            'isAdmin' => $user->isAdmin()
        ];
        return new JsonResponse($data, 200, [], true);
    }

    #[Route('/user/{id}/edit', name: 'api_user_update', methods: ['PUT'])]
    public function updateUser(Request $request, User $user,EntityManagerInterface $entityManager, ValidatorInterface $validator, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $user = $entityManager->getRepository(User::class)->find($user->getId());
        if (!$user) {
            return new JsonResponse(['message' => 'User not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $user->setName($data['name']);
        }
        if (isset($data['phone'])) {
            $user->setPhone($data['phone']);
        }

        if (isset($data['password'])) {
            $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);
        }

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['message' => 'Validation failed', 'errors' => $errorMessages], JsonResponse::HTTP_BAD_REQUEST);
        }

        $entityManager->flush();

        return $this->json($user, JsonResponse::HTTP_OK, []);
    }

}