# Rapport d'audit securite - CampusManager

## 1. Presentation du projet

CampusManager est une application web de gestion etudiant/cours developpee avec le framework **Symfony 7.4** (PHP), dans le cadre d'un exercice pedagogique de securite applicative et DevSecOps.

Le projet se compose de deux versions distinctes au sein du meme depot Git :

- **Branche `vulnerable`** : implementation fonctionnelle contenant volontairement 6 vulnerabilites web/API realistes, exploitables et documentees.
- **Branche `secure`** : la meme application, corrigee a la racine de chaque vulnerabilite, enrichie d'une pipeline CI/CD automatisant les controles de securite.

**Fonctionnalites principales :**
- Authentification (inscription / connexion via JWT)
- Deux roles utilisateurs : `ROLE_USER` (etudiant) et `ROLE_ADMIN`
- API REST permettant la gestion complete (CRUD) des inscriptions a des cours
- Interface web pour chaque role : dashboard etudiant (gestion de ses cours) et console admin (gestion des utilisateurs)

**Stack technique :** Symfony 7.4, Doctrine ORM, MySQL, JWT (LexikJWTAuthenticationBundle), Twig, JavaScript natif (fetch API).

**Objectif pedagogique :** demontrer une comprehension a la fois offensive (identification et exploitation de failles) et defensive (correction structurelle, durcissement, automatisation) de la securite des applications web modernes.

---

## 2. Architecture de l'application

### Entites

**Utilisateur**
| Champ | Type | Description |
|---|---|---|
| id | int | Identifiant unique |
| email | string | Identifiant de connexion |
| nomComplet | string, nullable | Nom affiche |
| motDePasse | string | Mot de passe hache |
| roles | array | `ROLE_USER` et/ou `ROLE_ADMIN` |

**Cours**
| Champ | Type | Description |
|---|---|---|
| id | int | Identifiant unique |
| titre | string | Titre du cours |
| description | text, nullable | Description libre |
| nomEnseignant | string, nullable | Enseignant du cours |
| statut | string | `disponible`, `complet` ou `annule` |
| proprietaire | relation vers Utilisateur | Etudiant inscrit a ce cours |

### Endpoints API

| Methode | Route | Acces | Description |
|---|---|---|---|
| POST | `/api/inscription` | Public | Creation de compte |
| POST | `/api/login` | Public | Authentification, retourne un JWT |
| GET | `/api/cours` | Authentifie | Liste des cours de l'utilisateur connecte |
| GET | `/api/cours/{id}` | Authentifie | Detail d'un cours |
| POST | `/api/cours` | Authentifie | Creation d'une inscription |
| PUT | `/api/cours/{id}` | Authentifie | Modification d'une inscription |
| DELETE | `/api/cours/{id}` | Authentifie | Suppression d'une inscription |
| GET | `/api/cours/recherche` | Authentifie | Recherche de cours par titre |
| GET | `/api/utilisateurs` | Admin | Liste des utilisateurs |
| GET | `/api/utilisateurs/{id}` | Admin | Detail d'un utilisateur |
| POST | `/api/utilisateurs` | Admin | Creation d'un compte |
| PATCH | `/api/utilisateurs/{id}` | Admin | Modification d'un compte |
| DELETE | `/api/utilisateurs/{id}` | Admin | Suppression d'un compte |
| GET | `/` | Public | Dashboard etudiant (HTML) |
| GET | `/admin` | Public (garde cote client, verifie cote serveur) | Console admin (HTML) |

### Interface

- **Page etudiant (`/`)** : connexion, creation de cours, liste de ses cours avec modification/suppression, recherche
- **Console admin (`/admin`)** : creation, listing, modification de role et suppression des utilisateurs

---

## 3. Installation et lancement

### Prerequis

PHP >= 8.2 (extension `sodium` activee), Composer, MySQL, Symfony CLI (optionnel).

### Installation

```bash
composer install

mkdir config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout

php bin/console doctrine:database:create
php bin/console doctrine:schema:create
php bin/console doctrine:fixtures:load
```

### Lancement

```bash
symfony server:start
```

Application accessible sur `http://127.0.0.1:8000`.

### Comptes de test

| Email | Mot de passe | Role |
|---|---|---|
| etudiant1@test.com | password123 | ROLE_USER |
| etudiant2@test.com | password123 | ROLE_USER |
| admin@test.com | adminpass123 | ROLE_USER, ROLE_ADMIN |

---

## 4. Organisation Git

Le depot contient trois branches :

- `main` : squelette Symfony initial, sans logique metier
- `vulnerable` : application complete avec les 6 failles volontaires
- `secure` : application corrigee avec pipeline CI/CD

Convention de commit adoptee : `type(portee): description`, avec des types en francais (`ajout`, `correction`, `maintenance`).

Exemples de commits significatifs :
- `ajout(vuln): ajout de l'application vulnerable avec CRUD pour les roles etudiant et admin`
- `correction(secure): reconstruction complete de l'application avec correction des 6vulnerabilites`

---

## 5. Liste des vulnerabilites integrees

| ID | Type | OWASP / API Top 10 | Endpoint concerne |
|---|---|---|---|
| VULN-01 | IDOR / BOLA | API3:2023 Broken Object Property Level Authorization | `GET/PUT/DELETE /api/cours/{id}` |
| VULN-01bis | Broken Access Control | API5:2023 Broken Function Level Authorization | `GET /api/utilisateurs` |
| VULN-02 | Injection SQL | A03:2021 Injection | `GET /api/cours/recherche` |
| VULN-03 | XSS stocke | A03:2021 Injection | Affichage des cours (`/`) |
| VULN-04 | Authentification faible | A07:2021 Identification and Authentication Failures | `POST /api/login` |
| VULN-05 | Mass Assignment | API6:2023 Unrestricted Access to Sensitive Business Flows | `POST /api/register`, `PATCH /api/utilisateurs/{id}`, `POST /api/cours` |
| VULN-06 | Security Misconfiguration | A05:2021 Security Misconfiguration | Configuration globale (`.env`, CORS, headers) |

---

## 6. Audit detaille des vulnerabilites

### VULN-01 — IDOR sur la consultation/modification/suppression de cours

**Type**
Broken Access Control / IDOR / BOLA

**Endpoint concerne**
`GET /api/cours/{id}`, `PUT /api/cours/{id}`, `DELETE /api/cours/{id}`

**Description**
Ces endpoints permettent a un utilisateur authentifie d'acceder a un cours a partir de son identifiant. Le backend ne verifie pas que ce cours appartient bien a l'utilisateur connecte.

**Cause technique**

Dans la branche `vulnerable`, `CoursController` :

```php
public function get(int $id, CoursRepository $repo): JsonResponse
{
    $cours = $repo->find($id);
    if (!$cours) {
        return $this->json(['error' => 'Cours introuvable'], 404);
    }
    return $this->json($cours->versTableau());
}
```

Le cours est recupere uniquement par son ID, sans jamais comparer `$cours->getProprietaire()` a l'utilisateur authentifie.

**Exploitation**

Un utilisateur connecte en tant que `etudiant1` recupere d'abord la liste de ses propres cours, puis modifie manuellement l'ID dans l'URL pour cibler un cours ne lui appartenant pas :

```powershell
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/login" -Method Post `
    -ContentType "application/json" `
    -Body '{"email":"etudiant1@test.com","motDePasse":"password123"}'
$token = $response.token

Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/cours/2" `
    -Headers @{ Authorization = "Bearer $token" }
```

Le cours 2 appartient a `etudiant2`.

**Preuve**

[CAPTURE 1 : terminal PowerShell montrant la commande ci-dessus executee sur la branche `vulnerable`, avec la reponse contenant `proprietaireEmail: etudiant2@test.com` alors que le token utilise est celui de `etudiant1`]

**Impact**

- Fuite de donnees personnelles (inscriptions a des cours d'autrui)
- Exposition d'informations d'un autre utilisateur sans autorisation
- Risque RGPD
- Perte de confiance utilisateur

**Criticite** : Elevee

**Correction appliquee**

Ajout d'une methode metier sur l'entite `Cours` :

```php
public function appartientA(Utilisateur $utilisateur): bool
{
    return $this->proprietaire?->getId() === $utilisateur->getId();
}
```

Utilisee systematiquement dans le controleur avant tout acces :

```php
private function trouverCoursOuEchouer(int $id, CoursRepository $repo): Cours|JsonResponse
{
    $cours = $repo->find($id);
    if (!$cours || !$cours->appartientA($this->getUser())) {
        return $this->json(['error' => 'Cours introuvable'], 404);
    }
    return $cours;
}
```

Cette methode est appelee par `get()`, `modifier()` et `supprimer()`, garantissant qu'un cours inaccessible renvoie systematiquement une 404 identique, qu'il n'existe pas ou qu'il appartienne a quelqu'un d'autre (pas de difference observable pour un attaquant).

**Validation apres correction**

```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/cours/2" `
    -Headers @{ Authorization = "Bearer $token" }
```

Reponse obtenue : `{"error":"Cours introuvable"}` (HTTP 404).

[CAPTURE 2 : terminal PowerShell montrant la meme commande sur la branche `secure`, avec la reponse 404]

---

### VULN-01bis — Broken Access Control sur la liste des utilisateurs

**Type**
Broken Function Level Authorization

**Endpoint concerne**
`GET /api/utilisateurs`

**Description**
Cette route devrait etre reservee aux administrateurs. Dans la version vulnerable, aucun controle de role n'est effectue : n'importe quel utilisateur authentifie peut lister tous les comptes de l'application.

**Cause technique**

Dans `config/packages/security.yaml` (branche `vulnerable`) :

```yaml
access_control:
    - { path: ^/api/utilisateurs, roles: IS_AUTHENTICATED_FULLY }
```

Et dans `UtilisateurController` :

```php
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class UtilisateurController extends AbstractController
```

Aucune de ces deux couches ne verifie `ROLE_ADMIN`.

**Exploitation**

```powershell
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/login" -Method Post `
    -ContentType "application/json" `
    -Body '{"email":"etudiant1@test.com","motDePasse":"password123"}'
$token = $response.token

Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/utilisateurs" `
    -Headers @{ Authorization = "Bearer $token" }
```

**Preuve**

[CAPTURE 3 : terminal PowerShell montrant la liste complete des utilisateurs (emails, roles) obtenue avec un compte etudiant simple, sur la branche `vulnerable`]

**Impact**

- Enumeration de tous les comptes de la plateforme (emails)
- Reconnaissance facilitee pour une attaque ulterieure (phishing cible, brute force sur des comptes precis)
- Exposition de la structure des roles

**Criticite** : Elevee

**Correction appliquee**

Dans `config/packages/security.yaml` (branche `secure`) :

```yaml
access_control:
    - { path: ^/api/utilisateurs, roles: ROLE_ADMIN }
```

Et dans `UtilisateurController` :

```php
#[IsGranted('ROLE_ADMIN')]
class UtilisateurController extends AbstractController
```

Defense en profondeur : le controle est applique a deux niveaux independants (routing et controleur), de sorte qu'un oubli sur l'un des deux n'ouvre pas la faille.

**Validation apres correction**

```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/utilisateurs" `
    -Headers @{ Authorization = "Bearer $token" }
```

Reponse obtenue : HTTP 403 Forbidden.

Avec un compte admin, la meme requete retourne bien la liste complete.

[CAPTURE 4 : terminal montrant le 403 avec le token etudiant, puis le succes avec le token admin, sur la branche `secure`]

---

### VULN-02 — Injection SQL sur la recherche de cours

**Type**
Injection (SQLi)

**Endpoint concerne**
`GET /api/cours/recherche?query=`

**Description**
L'endpoint de recherche construit une requete SQL native en concatenant directement le terme fourni par l'utilisateur, sans requete parametree.

**Cause technique**

Dans `CoursRepository` (branche `vulnerable`) :

```php
public function rechercheVulnerable(string $terme): array
{
    $conn = $this->getEntityManager()->getConnection();
    $sql = "SELECT c.id, c.titre, c.description, c.statut, c.proprietaire_id
            FROM cours c
            WHERE c.titre LIKE '%" . $terme . "%'";
    $stmt = $conn->executeQuery($sql);
    return $stmt->fetchAllAssociative();
}
```

**Exploitation**

```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/cours/recherche?query=x%27%20UNION%20SELECT%20id,email,motDePasse,1,1,1%20FROM%20utilisateur--%20-" `
    -Headers @{ Authorization = "Bearer $token" }
```

Le payload ferme la chaine de recherche (`x'`), puis ajoute une clause `UNION SELECT` sur la table `utilisateur` pour en extraire les emails et mots de passe hashes, et commente le reste de la requete d'origine (`-- -`).

**Preuve**

[CAPTURE 5 : terminal PowerShell montrant la reponse contenant des emails et hash de mots de passe issus de la table utilisateur, sur la branche `vulnerable`]

**Impact**

- Exfiltration de l'integralite de la table utilisateurs (emails, mots de passe hashes)
- Possibilite d'etendre l'attaque a d'autres tables ou a des operations d'ecriture selon les privileges du compte MySQL utilise
- Compromission potentielle de tous les comptes si les hashes sont casses

**Criticite** : Critique

**Correction appliquee**

Utilisation du QueryBuilder Doctrine avec parametre lie, dans `CoursRepository` (branche `secure`) :

```php
public function rechercherParTitre(string $terme): array
{
    return $this->createQueryBuilder('c')
        ->where('c.titre LIKE :terme')
        ->setParameter('terme', '%' . $terme . '%')
        ->getQuery()
        ->getResult();
}
```

Le terme de recherche est transmis comme parametre lie (bind parameter), jamais interprete comme du SQL executable, quelle que soit sa valeur.

**Validation apres correction**

Requete malveillante :
```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/cours/recherche?query=x%27%20UNION%20SELECT%20id,email,motDePasse,1,1,1%20FROM%20utilisateur--%20-" `
    -Headers @{ Authorization = "Bearer $token" }
```
Reponse obtenue : liste vide, aucune donnee de la table utilisateur exposee.

Requete legitime (verification de non-regression) :
```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/cours/recherche?query=Algo" `
    -Headers @{ Authorization = "Bearer $token" }
```
Reponse obtenue : le cours "Algorithmique avancee" est retourne normalement.

[CAPTURE 6 : terminal montrant les deux tests (payload sans effet + recherche normale toujours fonctionnelle) sur la branche `secure`]

---

### VULN-03 — XSS stocke sur la description d'un cours

**Type**
Cross-Site Scripting (Stored XSS)

**Endpoint concerne**
Creation/affichage de cours (`POST /api/cours`, affichage sur `/`)

**Description**
La description d'un cours, saisie librement par l'utilisateur, est injectee dans le DOM sans echappement au moment de l'affichage.

**Cause technique**

Dans `templates/app/accueil.html.twig` (branche `vulnerable`), la description est inseree via `innerHTML` :

```javascript
bloc.innerHTML = '<strong>' + c.titre + '</strong> — ' + c.statut + '<br>' + c.description;
```

Toute balise HTML/JavaScript contenue dans `c.description` est interpretee et executee par le navigateur.

**Exploitation**

Creation d'un cours avec la description suivante :

```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/cours" -Method Post `
    -ContentType "application/json" -Headers @{ Authorization = "Bearer $token" } `
    -Body '{"titre":"Test XSS","description":"<script>alert(1)</script>"}'
```

Des l'affichage de la liste des cours (chargement de `/`), le script s'execute dans le navigateur de la victime.

**Preuve**

[CAPTURE 7 : navigateur affichant une popup "1" au chargement de la page, sur la branche `vulnerable`]

**Impact**

- Execution de code JavaScript arbitraire dans le navigateur d'autres utilisateurs consultant les cours
- Vol de session possible (lecture du `localStorage`, y compris le token JWT)
- Actions effectuees a l'insu de la victime
- Defacement de l'interface

**Criticite** : Elevee

**Correction appliquee**

Remplacement de toute insertion `innerHTML` de donnees utilisateur par `textContent`, dans `templates/app/accueil.html.twig` (branche `secure`) :

```javascript
const titre = document.createElement('h3');
titre.textContent = c.titre;

const description = document.createElement('div');
description.className = 'description';
description.textContent = c.description || '';
```

`textContent` insere la valeur comme texte brut ; le contenu n'est jamais interprete comme du HTML/JavaScript par le navigateur.

**Validation apres correction**

Le meme cours "Test XSS" avec la description `<script>alert(1)</script>` est affiche : le texte apparait litteralement a l'ecran (chevrons visibles), aucune popup ne se declenche.

[CAPTURE 8 : navigateur affichant le texte brut `<script>alert(1)</script>` dans la carte du cours, sans popup, sur la branche `secure`]

---

### VULN-04 — Authentification faible

**Type**
Identification and Authentication Failures

**Endpoint concerne**
`POST /api/login`, `POST /api/inscription`

**Description**
Plusieurs faiblesses cumulees affectent le mecanisme d'authentification : hachage faible des mots de passe, absence de limitation des tentatives, messages d'erreur permettant l'enumeration de comptes, duree de vie excessive du token JWT.

**Cause technique**

Dans `AuthController` (branche `vulnerable`) :

```php
$utilisateur->setMotDePasse(md5($data['motDePasse'] ?? ''));
```

```php
if (!$utilisateur) {
    return $this->json(['error' => 'Aucun compte trouve pour cet email'], 401);
}
if (md5($motDePasse) !== $utilisateur->getPassword()) {
    return $this->json(['error' => 'Mot de passe incorrect'], 401);
}
```

Et dans `config/packages/lexik_jwt_authentication.yaml` :

```yaml
token_ttl: 31536000
```

(1 an, sans mecanisme de revocation)

**Exploitation**

- Un attaquant ayant obtenu la base de donnees peut casser les hashes MD5 tres rapidement (tables arc-en-ciel, force brute GPU)
- Aucun `login_throttling` configure : un script peut tester des milliers de mots de passe par minute sans blocage
- Les messages d'erreur differencies permettent de determiner si un email existe en base, avant meme de connaitre le mot de passe
- Un token JWT vole reste valide un an

**Preuve**

[CAPTURE 9 : capture du code source `AuthController.php` montrant `md5()`, sur la branche `vulnerable`]

[CAPTURE 10 (optionnelle) : script ou boucle de tentatives de connexion successives sans blocage]

**Impact**

- Compromission massive des mots de passe en cas de fuite de base de donnees
- Brute force facilite
- Enumeration des comptes valides, facilitant des attaques cibles
- Fenetre d'exploitation tres longue en cas de vol de token

**Criticite** : Elevee

**Correction appliquee**

Hachage via le hasher natif de Symfony (bcrypt/argon2 selon configuration serveur) :

```php
$utilisateur->setMotDePasse($hasheurMotDePasse->hashPassword($utilisateur, $motDePasse));
```

Message d'erreur generique, identique quel que soit le cas d'echec :

```php
$identifiantsValides = $utilisateur !== null
    && $hasheurMotDePasse->isPasswordValid($utilisateur, $motDePasse);

if (!$identifiantsValides) {
    return $this->json(['error' => 'Identifiants incorrects.'], 401);
}
```

Limitation des tentatives via `symfony/rate-limiter`, dans `config/packages/rate_limiter.yaml` :

```yaml
framework:
    rate_limiter:
        connexion_utilisateur:
            policy: sliding_window
            limit: 5
            interval: '15 minutes'
```

Appliquee dans le controleur :

```php
$limiteur = $this->connexionUtilisateurLimiter->create($request->getClientIp());
if (!$limiteur->consume(1)->isAccepted()) {
    return $this->json(['error' => 'Trop de tentatives. Reessayez plus tard.'], 429);
}
```

Duree de vie du token reduite, dans `config/packages/lexik_jwt_authentication.yaml` :

```yaml
token_ttl: 3600
```

**Validation apres correction**

Connexion avec identifiants valides : fonctionne normalement, token recu.

[CAPTURE 11 : terminal montrant une connexion reussie sur la branche `secure`, avec un hash bcrypt visible en base de donnees (`SELECT mot_de_passe FROM utilisateur`) au lieu d'un hash MD5]

---

### VULN-05 — Mass Assignment

**Type**
Unrestricted Access to Sensitive Business Flows / Mass Assignment

**Endpoint concerne**
`POST /api/inscription`, `PATCH /api/utilisateurs/{id}`, `POST /api/cours`

**Description**
Plusieurs endpoints appliquent directement les champs recus dans le corps de la requete a l'entite, sans liste blanche. Un client peut ainsi definir des champs sensibles qui ne devraient pas etre sous son controle (roles, proprietaire d'une ressource).

**Cause technique**

Dans `AuthController::inscription()` (branche `vulnerable`) :

```php
$utilisateur->setRoles($data['roles'] ?? ['ROLE_USER']);
```

Dans `CoursController::creer()` (branche `vulnerable`) :

```php
if (!empty($data['ownerId'])) {
    $proprietaire = $em->getRepository(Utilisateur::class)->find($data['ownerId']);
    if ($proprietaire) {
        $cours->setProprietaire($proprietaire);
    }
}
```

**Exploitation**

Auto-attribution du role administrateur a l'inscription :

```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/inscription" -Method Post `
    -ContentType "application/json" `
    -Body '{"email":"attaquant@test.com","motDePasse":"motdepasse123","roles":["ROLE_ADMIN"]}'
```

**Preuve**

[CAPTURE 12 : terminal montrant la creation d'un compte avec `roles:["ROLE_ADMIN"]`, puis une connexion reussie avec ce compte donnant acces a `/api/utilisateurs`, sur la branche `vulnerable`]

**Impact**

- Elevation de privileges directe (un simple visiteur devient administrateur)
- Attribution de ressources (cours) a des utilisateurs arbitraires
- Contournement complet du modele de permissions

**Criticite** : Critique

**Correction appliquee**

Le filtrage est porte structurellement par l'entite elle-meme plutot que par une verification eparpillee dans chaque controleur, dans `Utilisateur.php` (branche `secure`) :

```php
public const ROLES_AUTORISES = ['ROLE_USER', 'ROLE_ADMIN'];

public function setRoles(array $roles): static
{
    $this->roles = array_values(array_intersect($roles, self::ROLES_AUTORISES));
    return $this;
}
```

Et dans `AuthController::inscription()`, le role est desormais fige cote serveur, jamais lu depuis la requete :

```php
$utilisateur->setRoles(['ROLE_USER']);
```

Dans `CoursController::creer()`, le proprietaire est toujours l'utilisateur authentifie, sans possibilite de le surcharger :

```php
$cours->setProprietaire($this->getUser());
```
(aucune lecture de `ownerId` dans le corps de la requete)

**Validation apres correction**

```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/inscription" -Method Post `
    -ContentType "application/json" `
    -Body '{"email":"attaquant2@test.com","motDePasse":"motdepasse123","roles":["ROLE_ADMIN"]}'
```

Le compte est cree avec uniquement `ROLE_USER`, confirme par une tentative d'acces a `/api/utilisateurs` qui echoue en 403.

[CAPTURE 13 : terminal montrant la creation du compte puis l'echec d'acces admin, sur la branche `secure`]

---

### VULN-06 — Security Misconfiguration

**Type**
Security Misconfiguration / Information Disclosure

**Endpoint concerne**
Configuration globale de l'application

**Description**
Plusieurs elements de configuration exposent inutilement des informations ou elargissent la surface d'attaque : mode debug actif, fichier `.env` versionne avec des secrets en clair, CORS totalement ouvert.

**Cause technique**

Dans `.env` (branche `vulnerable`, commite dans le depot) :

```
APP_DEBUG=1
APP_SECRET=changeme123
DATABASE_URL="mysql://root:@127.0.0.1:3306/campusmanager?..."
CORS_ALLOW_ORIGIN=*
```

Ce fichier n'est pas present dans `.gitignore` sur cette branche.

**Exploitation**

- Toute erreur applicative (exception non geree) affiche une stack trace complete au client, revelant chemins serveur, requetes SQL, version des dependances
- Le depot Git expose directement les identifiants de connexion a la base de donnees et le secret applicatif
- `CORS_ALLOW_ORIGIN=*` combine a des requetes authentifiees permet a n'importe quel site tiers d'interroger l'API pour le compte d'une victime

**Preuve**

[CAPTURE 14 : capture GitHub montrant le fichier `.env` visible dans l'historique du depot sur la branche `vulnerable`, avec `APP_DEBUG=1` et les identifiants en clair]

[CAPTURE 15 : capture d'une stack trace complete affichee suite a une requete provoquant une erreur (ex: JSON malforme envoye a `/api/login`)]

**Impact**

- Divulgation d'informations techniques facilitant d'autres attaques (chemins, versions, structure de la base)
- Compromission directe de la base de donnees si les identifiants sont reels et le serveur MySQL accessible depuis l'exterieur
- Vol de donnees cross-origin via un site tiers malveillant

**Criticite** : Moyenne a Elevee (selon exposition reseau du serveur MySQL)

**Correction appliquee**

Dans `.env` (branche `secure`) :

```
APP_DEBUG=0
APP_SECRET=<valeur aleatoire longue>
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
```

Le fichier `.env` est desormais ignore par Git, dans `.gitignore` :

```
###> symfony/framework-bundle ###
/.env
```

Un fichier `.env.example` est fourni a la place, sans valeurs sensibles, pour documenter la structure attendue sans exposer de secrets reels.

**Validation apres correction**

- `APP_DEBUG=0` : une erreur applicative renvoie desormais une page d'erreur generique, sans details techniques
- `.env` absent du depot (verifie via `git log --all --full-history -- .env` sur la branche `secure`)
- CORS restreint : une requete envoyee depuis une origine non autorisee est bloquee par le navigateur

[CAPTURE 16 : page d'erreur generique (sans stack trace) obtenue suite a une requete provoquant une erreur, sur la branche `secure`]

[CAPTURE 17 : verification que `.env` n'apparait pas dans `git status`/`git log` sur la branche `secure`]

---

## 7. Pipeline securite

La branche `secure` integre une pipeline GitHub Actions (`.github/workflows/security.yml`), declenchee automatiquement sur chaque `push` et `pull_request` vers cette branche.

### Jobs de la pipeline

| Job | Outil | Objectif |
|---|---|---|
| `analyse-statique` | Semgrep (regles PHP) | Detection de patterns de code dangereux dans le code source |
| `audit-dependances` | `composer audit` | Recherche de vulnerabilites connues (CVE) dans les dependances |
| `detection-secrets` | Gitleaks | Recherche de secrets (cles, mots de passe) dans l'historique Git |
| `tests-applicatifs` | Verification syntaxique PHP | Validation de base que le code est executable |
| `scan-dynamique` | OWASP ZAP (baseline scan) | Test dynamique de l'application demarree, avec base MySQL temporaire |

Le job `scan-dynamique` demarre un service MySQL ephemere fourni par GitHub Actions, genere des cles JWT temporaires et un `.env.local` specifique a la pipeline, cree le schema de base de donnees, puis lance l'application pour que ZAP puisse tester reellement les routes exposees.

[CAPTURE 18 : capture de l'onglet GitHub Actions montrant l'ensemble des jobs de la pipeline, avec leur statut]

---

## 8. Resultats des scans

[A completer avec les captures d'ecran des rapports generes par chaque outil apres execution de la pipeline]

- **Semgrep** : [CAPTURE 19 - resultat du job SAST]
- **composer audit** : [CAPTURE 20 - resultat du job SCA]
- **Gitleaks** : [CAPTURE 21 - resultat du job secret scanning]
- **OWASP ZAP** : [CAPTURE 22 - rapport de scan DAST, alertes remontees]

---

## 9. Limites du projet

- Le scan DAST (OWASP ZAP baseline) teste la surface HTTP de l'application mais ne simule pas de scenario d'authentification complexe (connexion prealable avant scan des routes protegees) ; une configuration plus avancee de ZAP (mode authentifie) permettrait une couverture plus complete.
- Les tests applicatifs se limitent a une verification de syntaxe PHP ; des tests unitaires/fonctionnels (PHPUnit) sur la logique metier (ownership, filtrage des roles) renforceraient la non-regression des corrections de securite.
- La protection CSRF n'a pas ete traitee specifiquement, l'API etant con\u00e7ue en authentification stateless par JWT (hors champ des vulnerabilites demandees pour ce projet).
- Le rate limiting est applique uniquement sur la connexion ; l'inscription et la creation de ressources pourraient beneficier d'une limitation similaire contre les abus.
- Les tests de charge/deni de service ne font pas partie du perimetre de cet audit.

---

## 10. Conclusion

Ce projet a permis de reproduire un cycle complet de securite applicative : conception volontairement vulnerable, audit personnel des failles integrees, exploitation controlee, documentation, correction structurelle de chaque vulnerabilite, et automatisation de controles de securite via une pipeline CI/CD.

Les six vulnerabilites du OWASP/API Top 10 traitees (IDOR, Broken Access Control, Injection SQL, XSS stocke, authentification faible, Mass Assignment) ont chacune ete corrigees a la racine de leur cause technique plutot que par un filtrage superficiel des payloads connus, et chaque correction a ete validee par un test reproduisant l'exploitation initiale.

L'objectif n'etait pas de produire une application parfaite, mais de demontrer une comprehension offensive et defensive concrete de la securite web moderne, ainsi que la capacite a integrer ces controles dans un flux de developpement automatise.

---

## 11. Organisation du depot Git

```
campusmanager/
├── src/
│   ├── Entity/
│   │   ├── Utilisateur.php
│   │   └── Cours.php
│   ├── Repository/
│   │   ├── UtilisateurRepository.php
│   │   └── CoursRepository.php
│   ├── Controller/
│   │   ├── AuthController.php
│   │   ├── CoursController.php
│   │   ├── UtilisateurController.php
│   │   └── AppController.php
│   └── DataFixtures/
│       └── AppFixtures.php
├── templates/
│   ├── base.html.twig
│   └── app/
│       ├── accueil.html.twig
│       └── admin.html.twig
├── config/
│   ├── packages/
│   │   ├── security.yaml
│   │   ├── lexik_jwt_authentication.yaml
│   │   └── rate_limiter.yaml
│   └── jwt/ (non versionne)
├── .github/
│   └── workflows/
│       └── security.yml
├── .env (non versionne)
├── .env.example
├── .gitignore
├── SECURITY_AUDIT.md
└── README.md
```

Branches : `main`, `vulnerable`, `secure`.
