<?php

namespace App\DataFixtures;

use App\Entity\Cours;
use App\Entity\Utilisateur;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $etudiant1 = new Utilisateur();
        $etudiant1->setEmail('etudiant1@test.com');
        $etudiant1->setNomComplet('Etudiant Un');
        $etudiant1->setMotDePasse(md5('password123'));
        $etudiant1->setRoles(['ROLE_USER']);
        $manager->persist($etudiant1);

        $etudiant2 = new Utilisateur();
        $etudiant2->setEmail('etudiant2@test.com');
        $etudiant2->setNomComplet('Etudiant Deux');
        $etudiant2->setMotDePasse(md5('password123'));
        $etudiant2->setRoles(['ROLE_USER']);
        $manager->persist($etudiant2);

        $admin = new Utilisateur();
        $admin->setEmail('admin@test.com');
        $admin->setNomComplet('Administrateur');
        $admin->setMotDePasse(md5('adminpass123'));
        $admin->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $manager->persist($admin);

        $cours1 = new Cours();
        $cours1->setTitre('Algorithmique avancee');
        $cours1->setDescription('Inscription confidentielle de etudiant1 a ce cours.');
        $cours1->setNomEnseignant('M. Diop');
        $cours1->setStatut('disponible');
        $cours1->setProprietaire($etudiant1);
        $manager->persist($cours1);

        $cours2 = new Cours();
        $cours2->setTitre('Bases de donnees relationnelles');
        $cours2->setDescription("Informations sensibles liees a l'inscription de etudiant2.");
        $cours2->setNomEnseignant('Mme Fall');
        $cours2->setStatut('complet');
        $cours2->setProprietaire($etudiant2);
        $manager->persist($cours2);

        $manager->flush();
        
    }
}