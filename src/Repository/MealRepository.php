<?php

namespace App\Repository;

use App\Entity\Meal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Meal>
 */
class MealRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Meal::class);
    }

    //    /**
    //     * @return Meal[] Returns an array of Meal objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('m.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Meal
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
    public function findByCategories(array $categoryIds): array
    {
        $qb = $this->createQueryBuilder('m');

        if (!empty($categoryIds)) {
            $qb->join('m.categories', 'c')
               ->where($qb->expr()->in('c.id', ':categoryIds'))
               ->setParameter('categoryIds', $categoryIds);
        }

        return $qb->getQuery()->getResult();
    }

    // SELECT m.*
    // FROM meals m
    // JOIN meal_category mc ON m.id = mc.meal_id
    // JOIN categories c ON mc.category_id = c.id
    // WHERE c.id IN (:categoryIds)
}
