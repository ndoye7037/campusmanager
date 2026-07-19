<?php

namespace App\Repository;

use App\Entity\Cours;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CoursRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cours::class);
    }

    public function rechercherParTitre(string $terme): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.titre LIKE :terme')
            ->setParameter('terme', '%' . $terme . '%')
            ->getQuery()
            ->getResult();
    }

    public function trouverParProprietaire(Utilisateur $proprietaire): array
    {
        return $this->findBy(['proprietaire' => $proprietaire]);
    }
}