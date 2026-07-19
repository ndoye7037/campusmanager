<?php

namespace App\Controller;

use App\Entity\Cours;
use App\Repository\CoursRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class CoursController extends AbstractController
{
    #[Route('/api/cours', name: 'api_cours_liste', methods: ['GET'])]
    public function liste(CoursRepository $repo): JsonResponse
    {
        $cours = $repo->trouverParProprietaire($this->getUser());

        return $this->json(array_map(fn(Cours $c) => $c->versTableau(), $cours));
    }

    #[Route('/api/cours/recherche', name: 'api_cours_recherche', methods: ['GET'])]
    public function recherche(Request $request, CoursRepository $repo): JsonResponse
    {
        $terme = $request->query->get('query', '');
        $resultats = $repo->rechercherParTitre($terme);

        return $this->json(array_map(fn(Cours $c) => $c->versTableau(), $resultats));
    }

    #[Route('/api/cours/{id}', name: 'api_cours_get', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function get(int $id, CoursRepository $repo): JsonResponse
    {
        $cours = $this->trouverCoursOuEchouer($id, $repo);
        if ($cours instanceof JsonResponse) {
            return $cours;
        }

        return $this->json($cours->versTableau());
    }

    #[Route('/api/cours', name: 'api_cours_creer', methods: ['POST'])]
    public function creer(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $donnees = json_decode($request->getContent(), true) ?? [];

        if (empty($donnees['titre'])) {
            return $this->json(['error' => 'Le titre est obligatoire.'], 400);
        }

        $cours = new Cours();
        $cours->setTitre($donnees['titre']);
        $cours->setDescription($donnees['description'] ?? null);
        $cours->setNomEnseignant($donnees['nomEnseignant'] ?? null);
        $cours->setProprietaire($this->getUser());

        $em->persist($cours);
        $em->flush();

        return $this->json($cours->versTableau(), 201);
    }

    #[Route('/api/cours/{id}', name: 'api_cours_modifier', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function modifier(int $id, Request $request, CoursRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $cours = $this->trouverCoursOuEchouer($id, $repo);
        if ($cours instanceof JsonResponse) {
            return $cours;
        }

        $donnees = json_decode($request->getContent(), true) ?? [];

        if (isset($donnees['titre'])) {
            $cours->setTitre($donnees['titre']);
        }
        if (isset($donnees['description'])) {
            $cours->setDescription($donnees['description']);
        }
        if (isset($donnees['statut'])) {
            $cours->setStatut($donnees['statut']);
        }

        $em->flush();

        return $this->json($cours->versTableau());
    }

    #[Route('/api/cours/{id}', name: 'api_cours_supprimer', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function supprimer(int $id, CoursRepository $repo, EntityManagerInterface $em): Response
    {
        $cours = $this->trouverCoursOuEchouer($id, $repo);
        if ($cours instanceof JsonResponse) {
            return $cours;
        }

        $em->remove($cours);
        $em->flush();

        return new Response(null, 204);
    }

    private function trouverCoursOuEchouer(int $id, CoursRepository $repo): Cours|JsonResponse
    {
        $cours = $repo->find($id);

        if (!$cours || !$cours->appartientA($this->getUser())) {
            return $this->json(['error' => 'Cours introuvable'], 404);
        }

        return $cours;
    }
}