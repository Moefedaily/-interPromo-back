<?php

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\Table;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Repository\TableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ReservationService
{
    private $reservationRepository;
    private $tableRepository;
    private $entityManager;
    private $logger;

    public function __construct(
        ReservationRepository $reservationRepository,
        TableRepository $tableRepository,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger

    ) {
        $this->reservationRepository = $reservationRepository;
        $this->tableRepository = $tableRepository;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public function createReservation(Reservation $reservation): ?Reservation
    {
        try {
            $dateTime = $reservation->getDate();
            $npPeople = $reservation->getNpPeople();
            $service = $reservation->getService();
            
            if ($service === null) {
                throw new \InvalidArgumentException('Reservation time is outside of service hours.');
            }
            
            if($dateTime < new \DateTime()) {
                throw new \InvalidArgumentException('Reservation date is in the past.');
            }
          
    
            $requiredTables = $this->calculateRequiredTables($npPeople);
            $availableTables = $this->getAvailableTables($dateTime, $service);
            
            $this->logger->debug("Required tables: {$requiredTables}");
            $this->logger->debug("Available tables: " . count($availableTables));
            
            if (count($availableTables) < $requiredTables) {
                $this->logger->warning("Not enough tables available for reservation");
                return null;
            }
            
            for ($i = 0; $i < $requiredTables; $i++) {
                if (!isset($availableTables[$i])) {
                    $this->logger->error("Unexpected error: Available table not found at index {$i}");
                    return null;
                }
                $reservation->addTable($availableTables[$i]);
            }
            
            $this->entityManager->persist($reservation);
            $this->entityManager->flush();
            
            return $reservation;
        } catch (\Exception $e) {
            $this->logger->error("Error creating reservation: " . $e->getMessage());
            throw $e; 
        }
    }

public function checkExistingReservation(User $user, \DateTime $dateTime, string $service): ?Reservation
    {
        return $this->reservationRepository->findOneBy([
            'user' => $user,
            'date' => $dateTime,
            'service' => $service
        ]);
    }

    private function calculateRequiredTables(int $npPeople): int
    {
        return ceil($npPeople / 2);
    }

   

    private function getAvailableTables(\DateTime $dateTime, string $service): array
{
    $allTables = $this->tableRepository->findAll();
    $reservedTableIds = $this->reservationRepository->getReservedTableIds($dateTime, $service);
    
    $this->logger->debug("All tables count: " . count($allTables));
    $this->logger->debug("Reserved table IDs: " . json_encode($reservedTableIds));

    $availableTables = array_filter($allTables, function(Table $table) use ($reservedTableIds) {
        return !in_array($table->getId(), $reservedTableIds);
    });

    $this->logger->debug("Available tables count: " . count($availableTables));

    return array_values($availableTables);
}


    public function getWeekAvailability(\DateTime $startDate, \DateTime $endDate): array
    {
        $this->logger->debug("Getting week availability from {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");

        $availability = [];
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $lunchAvailability = $this->checkAvailability($currentDate, 'lunch');
            $dinnerAvailability = $this->checkAvailability($currentDate, 'dinner');

            $availability[$currentDate->format('Y-m-d')] = [
                'lunch' => $lunchAvailability,
                'dinner' => $dinnerAvailability,
            ];

            $currentDate->modify('+1 day');
        }

        $this->logger->debug("Week availability result: " . json_encode($availability));

        return $availability;
    }

    private function checkAvailability(\DateTime $date, string $service): int
    {
        $this->logger->debug("Checking availability for date: {$date->format('Y-m-d')} and service: {$service}");

        $reservedTables = $this->reservationRepository->countReservedTables($date, $service);
        $this->logger->debug("Reserved tables: {$reservedTables}");

        $availableTables = $_ENV['TOTAL_TABLES'] - $reservedTables;

        $this->logger->debug("Available tables: {$availableTables}");

        return max(0, $availableTables);
    }


    public function updateReservation(Reservation $reservation, array $data): ?Reservation
    {
        $this->logger->info('Updating reservation', ['id' => $reservation->getId(), 'data' => $data]);

        try {
            if (isset($data['date'])) {
                $reservation->setDate(new \DateTime($data['date']));
            }
            if (isset($data['service'])) {
                $reservation->setService($data['service']);
            }
            if (isset($data['npPeople'])) {
                $reservation->setNpPeople((int)$data['npPeople']);
            }

            $requiredTables = ceil($reservation->getNpPeople() / 2);

            if (isset($data['table_ids'])) {
                foreach ($reservation->getTables() as $table) {
                    $reservation->removeTable($table);
                }

                $addedTables = 0;
                foreach ($data['table_ids'] as $tableId) {
                    if ($addedTables >= $requiredTables) {
                        break;
                    }
                    $table = $this->tableRepository->find($tableId);
                    if ($table) {
                        $reservation->addTable($table);
                        $addedTables++;
                    } else {
                        $this->logger->warning('Table not found', ['table_id' => $tableId]);
                    }
                }

                if ($addedTables < $requiredTables) {
                    $availableTables = $this->getAvailableTables($reservation->getDate(), $reservation->getService());
                    foreach ($availableTables as $table) {
                        if ($addedTables >= $requiredTables) {
                            break;
                        }
                        if (!$reservation->getTables()->contains($table)) {
                            $reservation->addTable($table);
                            $addedTables++;
                        }
                    }
                }
            }

            $this->entityManager->flush();

            $this->logger->info('Reservation updated successfully', [
                'id' => $reservation->getId(),
                'npPeople' => $reservation->getNpPeople(),
                'tables' => $reservation->getTables()->map(fn($table) => $table->getId())->toArray()
            ]);

            return $reservation;
        } catch (\Exception $e) {
            $this->logger->error("Error updating reservation: " . $e->getMessage());
            throw $e;
        }
    }
}