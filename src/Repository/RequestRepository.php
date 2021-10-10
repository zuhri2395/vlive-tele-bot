<?php
namespace App\Repository;

use App\Entity\Request;
use Doctrine\ORM\EntityRepository;
use function Doctrine\ORM\QueryBuilder;

/**
 * @method Request|null find($id, $lockMode = null, $lockVersion = null)
 * @method Request|null findOneBy(array $criteria, array $orderBy = null)
 * @method Request[]    findAll()
 * @method Request[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RequestRepository extends EntityRepository
{
    public function findExistingFileId($link, $quality, $subs)
    {
        $qb = $this->createQueryBuilder('r');
        return $qb->where('r.link = :link')
                    ->select('r.file_id')
                    ->andWhere('r.quality = :quality')
                    ->andWhere('r.subs = :subs')
                    ->andWhere($qb->expr()->isNotNull('r.file_id'))
                    ->setMaxResults(1)
                    ->setParameter('quality', $quality)
                    ->setParameter('link', $link)
                    ->setParameter('subs', $subs)
                    ->getQuery()
                    ->getResult();
    }
    // /**
    //  * @return LeadData[] Returns an array of LeadData objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('l.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?LeadData
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}