<?php

namespace App\Repository;

use App\Entity\Quote;
use App\Entity\User;
use App\Enum\QuoteStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Quote>
 */
class QuoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quote::class);
    }

    public function searchQueryBuilder(User $owner, ?QuoteStatus $status = null, ?string $term = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('q')
            ->addSelect('c', 'l')
            ->join('q.client', 'c')
            ->leftJoin('q.lines', 'l')
            ->andWhere('q.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('q.issueDate', 'DESC')
            ->addOrderBy('q.id', 'DESC');

        if (null !== $status) {
            $qb->andWhere('q.status = :status')->setParameter('status', $status);
        }

        if (null !== $term && '' !== trim($term)) {
            $qb->andWhere('LOWER(q.number) LIKE :term OR LOWER(c.name) LIKE :term OR LOWER(q.title) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower(trim($term)).'%');
        }

        return $qb;
    }

    /**
     * @return list<Quote>
     */
    public function findRecent(User $owner, int $limit = 5): array
    {
        return $this->createQueryBuilder('q')
            ->addSelect('c')
            ->join('q.client', 'c')
            ->andWhere('q.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('q.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, int> nombre de devis par statut (valeur de l'enum)
     */
    public function countByStatus(User $owner): array
    {
        $rows = $this->createQueryBuilder('q')
            ->select('q.status AS status, COUNT(q.id) AS total')
            ->andWhere('q.owner = :owner')
            ->setParameter('owner', $owner)
            ->groupBy('q.status')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $status = $row['status'];
            $counts[$status instanceof QuoteStatus ? $status->value : (string) $status] = (int) $row['total'];
        }

        return $counts;
    }

    /** Dernier numéro utilisé pour un préfixe donné (ex. « DV-2026- »), null s'il n'y en a pas. */
    public function findLastNumber(User $owner, string $prefix): ?string
    {
        $result = $this->createQueryBuilder('q')
            ->select('q.number')
            ->andWhere('q.owner = :owner')
            ->andWhere('q.number LIKE :prefix')
            ->setParameter('owner', $owner)
            ->setParameter('prefix', $prefix.'%')
            ->orderBy('q.number', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['number'] ?? null;
    }
}
