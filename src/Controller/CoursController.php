<?php

namespace App\Controller;

use App\Entity\Cours;
use App\Entity\Utilisateur;
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
        $utilisateur = $this->getUser();
        $cours = $repo->findBy(['proprietaire' => $utilisateur]);

        return $this->json(array_map(fn(Cours $c) => $c->versTableau(), $cours));
    }

    #[Route('/api/cours/{id}', name: 'api_cours_get', methods: ['GET'])]
    public function get(int $id, CoursRepository $repo): JsonResponse
    {
        $cours = $repo->find($id);

        if (!$cours) {
            return $this->json(['error' => 'Cours introuvable'], 404);
        }

        return $this->json($cours->versTableau());
    }

    #[Route('/api/cours', name: 'api_cours_creer', methods: ['POST'])]
    public function creer(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $cours = new Cours();
        $cours->setTitre($data['titre'] ?? '(sans titre)');
        $cours->setDescription($data['description'] ?? null);
        $cours->setNomEnseignant($data['nomEnseignant'] ?? null);
        $cours->setStatut($data['statut'] ?? 'disponible');
        $cours->setProprietaire($this->getUser());

        if (!empty($data['proprietaireId'])) {
            $proprietaire = $em->getRepository(Utilisateur::class)->find($data['proprietaireId']);
            if ($proprietaire) {
                $cours->setProprietaire($proprietaire);
            }
        }

        $em->persist($cours);
        $em->flush();

        return $this->json($cours->versTableau(), 201);
    }

    #[Route('/api/cours/{id}', name: 'api_cours_modifier', methods: ['PUT'])]
    public function modifier(int $id, Request $request, CoursRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $cours = $repo->find($id);

        if (!$cours) {
            return $this->json(['error' => 'Cours introuvable'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['titre'])) $cours->setTitre($data['titre']);
        if (isset($data['description'])) $cours->setDescription($data['description']);
        if (isset($data['nomEnseignant'])) $cours->setNomEnseignant($data['nomEnseignant']);
        if (isset($data['statut'])) $cours->setStatut($data['statut']);

        $em->flush();

        return $this->json($cours->versTableau());
    }

    #[Route('/api/cours/{id}', name: 'api_cours_supprimer', methods: ['DELETE'])]
    public function supprimer(int $id, CoursRepository $repo, EntityManagerInterface $em): Response
    {
        $cours = $repo->find($id);

        if (!$cours) {
            return $this->json(['error' => 'Cours introuvable'], 404);
        }

        $em->remove($cours);
        $em->flush();

        return new Response(null, 204);
    }

    #[Route('/api/cours/recherche', name: 'api_cours_recherche', methods: ['GET'])]
    public function recherche(Request $request, CoursRepository $repo): JsonResponse
    {
        $terme = $request->query->get('query', '');

        $resultats = $repo->rechercheVulnerable($terme);

        return $this->json($resultats);
    }

    #[Route('/cours/vue', name: 'cours_vue_html', methods: ['GET'])]
    public function vueHtml(CoursRepository $repo): Response
    {
        $cours = $repo->findBy(['proprietaire' => $this->getUser()]);

        return $this->render('cours/liste.html.twig', ['cours' => $cours]);
    }
}