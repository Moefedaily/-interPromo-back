<?php

namespace App\Repository;

use App\Entity\Reservation;
use App\Entity\Table;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    private $logger;
    public function __construct(ManagerRegistry $registry, LoggerInterface $logger)
    {
        parent::__construct($registry, Reservation::class);
        $this->logger = $logger;
    }

    //    /**
    //     * @return Reservation[] Returns an array of Reservation objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Reservation
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }


  
    public function getReservedTableIds(\DateTime $date, string $service): array
    {
        $result = $this->createQueryBuilder('r')
            ->select('DISTINCT t.id')
            ->join('r.tables', 't')
            ->where('r.date = :date')
            ->andWhere('r.service = :service')
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('service', $service)
            ->getQuery()
            ->getResult();
    
        return array_column($result, 'id');

    
    // SELECT t.*
    // FROM `table` t
    // WHERE t.id NOT IN (
    // SELECT DISTINCT t.id
    // FROM reservation r
    // JOIN reservation_table rt ON r.id = rt.reservation_id
    // WHERE r.date = 'date' AND r.service = 'service'
    // )

}
    public function getAllTables(): array
    {
        return $this->getEntityManager()
            ->getRepository(Table::class)
            ->findAll();
    }
    public function countReservedTables(\DateTime $date, string $service): int
    {
        $this->logger->debug("Counting reserved tables for date: {$date->format('Y-m-d')} and service: {$service}");

        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(DISTINCT t.id)')
            ->join('r.tables', 't')
            ->where('(r.date) = :date')
            ->andWhere('r.service = :service')
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('service', $service);

        $query = $qb->getQuery();
        $count = $query->getSingleScalarResult();

        $this->logger->debug("Reserved tables count: {$count}");

        return $count;
    }

}