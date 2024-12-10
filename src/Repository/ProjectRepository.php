<?php

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Project|null find($id, $lockMode = null, $lockVersion = null)
 * @method Project|null findOneBy(array $criteria, array $orderBy = null)
 * @method Project[]    findAll()
 * @method Project[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function findBySearch($searchQuery): void
    {

        /*
        $item = new Item();
        $item->setTitle('Baboons Invade Seaworld');
        $item->setDescription('In a crazy turn of events a pack a rabid red baboons invade Seaworld. Officials say that the Dolphins are being held hostage');
        $this->em->persist($item);
        $this->em->flush();
        */

        $qb = $this->createQueryBuilder('i');
        $qb
            ->andWhere('tsquery(i.name,:searchQuery) = true')
            //->orWhere("i.title LIKE '%$searchQuery%'")
            ->setParameter('searchQuery', $searchQuery)
        ;

        $query = $qb->getQuery();
        $result = $query->getArrayResult();
        ;

        dd($query->getParameters(), $query->getSQL(), $result);

    }

    // /**
    //  * @return project[] Returns an array of project objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?project
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
