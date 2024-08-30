<?php

namespace App\Controller;

use App\Entity\Meal;
use App\Repository\MealRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;


#[Route('/api/meal')]
class ApiMealController extends AbstractController
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    #[Route('s', name: 'app_api_meal', methods: ['GET'])]
    public function index(MealRepository $mealRepository, SerializerInterface $serializer): Response
    {

        $meals = $mealRepository->findAll();
        $data = $serializer->serialize($meals, 'json', ['groups' => 'api_meal']);
        return new JsonResponse($data, 200, [], true);
    }

    #[Route('/new', name: 'app_api_meal_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $meal = new Meal();
        $meal->setName($data['name']);
        $meal->setDescription($data['description']);
        $meal->setPrice($data['price']);
        $meal->setPicture($data['picture']);


        $errors = $validator->validate($meal);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['message' => 'Validation failed', 'errors' => $errorMessages], JsonResponse::HTTP_BAD_REQUEST);
        }

        $entityManager->persist($meal);
        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Meal added successfully',
            'meal' => [
                'name' => $meal->getName(),
                'description' => $meal->getDescription(),
                'price' => $meal->getPrice(),
                'picture' => $meal->getPicture()    
            ]
        ], JsonResponse::HTTP_CREATED);
    }

   

    #[Route('/{id}/edit', name: 'app_api_meal_edit', methods: ['PUT', 'PATCH'])]
    public function edit(Request $request, Meal $meal, EntityManagerInterface $entityManager, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (isset($data['name'])) {
            $meal->setName($data['name']);
        }
        if (isset($data['description'])) {
            $meal->setDescription($data['description']);
        }
        if (isset($data['price'])) {
            $price = floatval($data['price']);
            $meal->setPrice($price);
        }
        if (isset($data['picture'])) {
            $meal->setPicture($data['picture']); 
        }

        $errors = $validator->validate($meal);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['message' => 'Validation failed', 'errors' => $errorMessages], JsonResponse::HTTP_BAD_REQUEST);
        }

        $entityManager->persist($meal);
        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Meal modified successfully',
            'meal' => [
                'id' => $meal->getId(),
                'name' => $meal->getName(),
                'description' => $meal->getDescription(),
                'price' => $meal->getPrice(),
                'picture' => $meal->getPicture()
            ]
        ], JsonResponse::HTTP_CREATED);

    }
    #[Route('/{id}', name: 'app_api_meal_delete', methods: ['DELETE'])]
    public function delete(Meal $meal, EntityManagerInterface $entityManager): JsonResponse
    {
        $entityManager->remove($meal);
        $entityManager->flush();
        return new JsonResponse(['message' => 'Meal deleted successfully'], JsonResponse::HTTP_OK);
    }

    #[Route('/search', name: 'api_meals_search', methods: ['GET'])]
    public function search(Request $request, MealRepository $mealRepository): JsonResponse
    {
        $categories = $request->query->all()['categories'] ?? [];

        if (!is_array($categories)) {
            $categories = [$categories];
        }

        $categoryIds = array_map('intval', $categories);

        $meals = $mealRepository->findByCategories($categoryIds);

        return $this->json($meals, 200, [], ['groups' => 'api_meal']);
    }
    #[Route('/{id}', name: 'app_api_meal_show', methods: ['GET'])]
    public function show(Meal $meal, SerializerInterface $serializer): JsonResponse
    {
        $data = $serializer->serialize($meal, 'json', ['groups' => 'api_meal']);
        return new JsonResponse($data, 200, [], true);
    }


}