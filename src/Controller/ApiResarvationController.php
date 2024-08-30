<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Table;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/reservation')]
class ApiResarvationController extends AbstractController
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    #[Route('s', name: 'reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): JsonResponse
    {
        $reservations = $reservationRepository->findAll();
        return $this->json($reservations, Response::HTTP_OK, [], ['groups' => 'api_reservation']);
    }

    #[Route('/new', name: 'reservation_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator, ReservationService $reservationService): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
    
            $reservation = new Reservation();
            $reservation->setDate(new \DateTime($data['date']));
            $reservation->setService($data['service']);
            $reservation->setNpPeople($data['npPeople']);
            $reservation->setStatus('pending');
    
            if (isset($data['user_id'])) {
                $user = $entityManager->getRepository(User::class)->find($data['user_id']);
                if (!$user) {
                    return $this->json(['message' => 'User not found'], Response::HTTP_BAD_REQUEST);
                }
                $reservation->setUser($user);
            }
    
            $errors = $validator->validate($reservation);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json(['message' => 'Validation failed', 'errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }
    
            $createdReservation = $reservationService->createReservation($reservation);
            if ($createdReservation === null) {
                return $this->json(['message' => 'Unable to create reservation. No tables available.'], Response::HTTP_BAD_REQUEST);
            }
            return $this->json($createdReservation, Response::HTTP_CREATED, [], ['groups' => 'api_reservation']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(['message' => 'An unexpected error occurred'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    

    
    #[Route('/{id}/edit', name: 'reservation_update', methods: ['PUT', 'PATCH'])]
    public function update(Request $request, Reservation $reservation, ReservationService $reservationService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->logger->info('Received update data', ['data' => $data]);

        try {
            $updatedReservation = $reservationService->updateReservation($reservation, $data);

            $this->logger->info('Reservation updated', [
                'id' => $updatedReservation->getId(),
                'npPeople' => $updatedReservation->getNpPeople(),
                'tables' => $updatedReservation->getTables()->map(fn($table) => $table->getId())->toArray()
            ]);

            return $this->json($updatedReservation, JsonResponse::HTTP_OK, [], ['groups' => 'api_reservation']);
        } catch (\Exception $e) {
            $this->logger->error('Error updating reservation', ['error' => $e->getMessage()]);
            return $this->json(['error' => 'Failed to update reservation'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    #[Route('/{id}', name: 'reservation_delete', methods: ['DELETE'])]
    public function delete(Reservation $reservation, EntityManagerInterface $entityManager): JsonResponse
    {
        $entityManager->remove($reservation);
        $entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT,['groups' => 'api_reservation']);
    }
    #[Route('/availability', name: 'get_availablity', methods: ['GET'])]
    public function getAvailableSeats(Request $request, ReservationService $reservationService): JsonResponse
    {
        $startDate = new \DateTime($request->query->get('start_date', 'now'));
        $endDate = (clone $startDate)->modify('+6 days');
        try {
            $availability = $reservationService->getWeekAvailability($startDate, $endDate);
            return $this->json($availability);
        } catch (\Exception $e) {
            return $this->json(['error' => 'An error occurred while fetching availability'], 500);
        }
    }
    #[Route('/check', name: 'check_existing_reservation', methods: ['POST'])]
    public function checkExistingReservation(Request $request, ReservationService $reservationService, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['user_id']) || !isset($data['date']) || !isset($data['service'])) {
                return $this->json(['message' => 'Missing required fields'], JsonResponse::HTTP_BAD_REQUEST);
            }
    
            $userId = $data['user_id'];
            $date = new \DateTime($data['date']);
            $service = $data['service'];
    
            $user = $entityManager->getRepository(User::class)->find($userId);
            if (!$user) {
                return $this->json(['message' => 'User not found'], JsonResponse::HTTP_NOT_FOUND);
            }
    
            $existingReservation = $reservationService->checkExistingReservation($user, $date, $service);
    
            if ($existingReservation) {
                return $this->json([
                    'exists' => true,
                    'id' => $existingReservation->getId()
                ], JsonResponse::HTTP_OK);
            } else {
                return $this->json(['exists' => false], JsonResponse::HTTP_OK);
            }
        } catch (\Exception $e) {
            return $this->json(['message' => 'An error occurred: ' . $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        }
    #[Route('/{id}', name: 'reservation_show', methods: ['GET'])]
    public function show(Reservation $reservation): JsonResponse
    {
        return $this->json($reservation, Response::HTTP_OK, [], ['groups' => 'api_reservation']);}
    
}