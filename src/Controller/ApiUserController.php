<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ApiUserController extends AbstractController
{
    #[Route('/api')]
        #[Route('/login', name: 'app_api_login', methods: ['POST'])]
        public function login(
            Request $request, 
            UserRepository $userRepository, 
            UserPasswordHasherInterface $passwordHasher
        ): JsonResponse
        {
            $data = json_decode($request->getContent(), true);
            $email = $data['email'];
            $password = $data['password'] ;
    
            if (!$email || !$password) {
                return new JsonResponse(['message' => 'Email and password are required.'], Response::HTTP_BAD_REQUEST);
            }
    
            $user = $userRepository->findOneBy(['email' => $email]);
    
            if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
                return new JsonResponse(['message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
            }
    
            return new JsonResponse([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail()
                ]
            ], Response::HTTP_OK);
        }

        #[Route('/register', name: 'app_api_register', methods: ['POST'])]
    public function register(
        Request $request, 
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $user = new User();
        $user->setMail($data['email']);
        $user->setPassword($data['password']);

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['message' => 'Validation failed', 'errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $hashedPassword = $passwordHasher->hashPassword($user, $user->getPassword());
        $user->setPassword($hashedPassword);

        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse([
            'message' => 'User registered successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getMail(),
            ]
        ], Response::HTTP_CREATED);
    }
    }   
