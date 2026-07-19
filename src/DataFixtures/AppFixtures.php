<?php

namespace App\DataFixtures;

use App\Entity\Cours;
use App\Entity\Utilisateur;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasheurMotDePasse,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $etudiant1 = $this->creerUtilisateur('etudiant1@test.com', 'Etudiant Un', 'password123', ['ROLE_USER']);
        $etudiant2 = $this->creerUtilisateur('etudiant2@test.com', 'Etudiant Deux', 'password123', ['ROLE_USER']);
        $admin = $this->creerUtilisateur('admin@test.com', 'Administrateur', 'adminpass123', ['ROLE_USER', 'ROLE_ADMIN']);

        $manager->persist($etudiant1);
        $manager->persist($etudiant2);
        $manager->persist($admin);

        $cours1 = new Cours();
        $cours1->setTitre('Algorithmique avancee');
        $cours1->setDescription('Inscription de etudiant1 a ce cours.');
        $cours1->setNomEnseignant('M. Diop');
        $cours1->setProprietaire($etudiant1);
        $manager->persist($cours1);

        $cours2 = new Cours();
        $cours2->setTitre('Bases de donnees relationnelles');
        $cours2->setDescription('Inscription de etudiant2 a ce cours.');
        $cours2->setNomEnseignant('Mme Fall');
        $cours2->setStatut('complet');
        $cours2->setProprietaire($etudiant2);
        $manager->persist($cours2);

        $manager->flush();
    }

    private function creerUtilisateur(string $email, string $nomComplet, string $motDePasse, array $roles): Utilisateur
    {
        $utilisateur = new Utilisateur();
        $utilisateur->setEmail($email);
        $utilisateur->setNomComplet($nomComplet);
        $utilisateur->setRoles($roles);
        $utilisateur->setMotDePasse($this->hasheurMotDePasse->hashPassword($utilisateur, $motDePasse));

        return $utilisateur;
    }
}