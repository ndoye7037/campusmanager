<?php

namespace App\Repository;

use App\Entity\Cours;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CoursRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cours::class);
    }

    public function rechercheVulnerable(string $terme): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "SELECT c.id, c.titre, c.description, c.nom_enseignant, c.statut, c.proprietaire_id
                FROM cours c
                WHERE c.titre LIKE '%" . $terme . "%'";

        $stmt = $conn->executeQuery($sql);

        return $stmt->fetchAllAssociative();
    }
}