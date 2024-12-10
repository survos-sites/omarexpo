<?php

namespace App\Repository;

use App\Entity\Item;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use function Symfony\Component\String\u;

/**
 * @method Item|null find($id, $lockMode = null, $lockVersion = null)
 * @method Item|null findOneBy(array $criteria, array $orderBy = null)
 * @method Item[]    findAll()
 * @method Item[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    public function findBySearch($searchQuery)
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
            ->andWhere('tsquery(i.title,:searchQuery) = true')
            //->orWhere("i.title LIKE '%$searchQuery%'")
            ->setParameter('searchQuery', $searchQuery)
            ;

        $query = $qb->getQuery();
        $result = $query->getResult();
        ;

        return $result;

        dd($query->getParameters(), $query->getSQL(), $result);

    }

        public function findByAttribute($attribute, $value) {
        ;

        $q = $this->createQueryBuilder('i')
            // ->select('JSON_GET_TEXT(i.attributes, :attribute)')
            ->andWhere("JSON_GET_TEXT(i.attributes, :attribute) = :value")
            ->getQuery();

        $result = $q->execute([
            'attribute' => $attribute,
            'value' => u($value)->toString()
        ]);

        /*
        dump($q->getSQL(), $attribute, $value, json_encode($result, JSON_PRETTY_PRINT), count($result));
        foreach ($result as $item) {
            dd($item);
            dd($item->getAttributes());
        }
        dd($q->getSQL(), $q, $attribute, $value);
        dd($result);
        */
        return $result;
    }

    /**
    * @return Item[] Returns an array of Exhibit objects
    */
    public function findWithAudio($limit=10, $language='es')
    {
        return $this->createQueryBuilder('e')
//            ->andWhere('e.s3Url is not NULL')
//            ->andWhere('e.duration > 0')
            ->orderBy('e.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

}
