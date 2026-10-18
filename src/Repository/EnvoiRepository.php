<?php

namespace App\Repository;

use App\Entity\Envoi;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Envoi>
 */
class EnvoiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Envoi::class);
    }

    public function findOneByReference(string $reference): ?Envoi
    {
        return $this->findOneBy(['reference' => Envoi::normaliserReference($reference)]);
    }
}
