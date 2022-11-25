<?php

namespace App\Repository;

use App\Entity\Paste;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Paste>
 *
 * @method Paste|null find($id, $lockMode = null, $lockVersion = null)
 * @method Paste|null findOneBy(array $criteria, array $orderBy = null)
 * @method Paste[]    findAll()
 * @method Paste[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PasteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Paste::class);
    }

    public function findByUrl(string $url): ?Paste
    {
        $result = $this->createQueryBuilder('p')
            ->andWhere('p.url = :val')
            ->setParameter('val', $url)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult()
            ;
        if ($result) {
            return $result[0];
        } else {
            return null;
        }
    }

    public function findByUrlAndUser(string $url, User $user): ?Paste
    {
        $result = $this->createQueryBuilder('p')
            ->andWhere('p.url = :url')
            ->setParameter('url', $url)
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult()
        ;
        if ($result) {
            return $result[0];
        } else {
            return null;
        }
    }


    public function getRandomUrl()
    {

        $paste = new Paste();

        while (!is_null($paste)) {
            $url = substr(md5(rand()), 0, 6);
            $paste = $this->findByUrl($url);
        }
        return $url;
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(Paste $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

}
