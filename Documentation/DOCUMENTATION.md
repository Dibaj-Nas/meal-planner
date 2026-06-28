# 🍽️ Planificateur de Repas — Documentation du projet

> Projet de fin de formation — Application web de planification de repas hebdomadaires
> Réalisé par **Dibaj NASRULLAH & JJ**

---

## 📑 Sommaire

1. [Présentation](#1-présentation)
2. [Fonctionnalités](#2-fonctionnalités)
3. [Stack technique](#3-stack-technique)
4. [Architecture](#4-architecture)
5. [Arborescence du projet](#5-arborescence-du-projet)
6. [Base de données](#6-base-de-données)
7. [Routage & API](#7-routage--api)
8. [Sécurité](#8-sécurité)
9. [Système d'e-mails (PHPMailer)](#9-système-demails-phpmailer)
10. [Installation & configuration](#10-installation--configuration)
11. [Guide d'utilisation](#11-guide-dutilisation)
12. [Parcours du code (pour aller plus loin)](#12-parcours-du-code)

---

## 1. Présentation

Le **Planificateur de Repas** est une application web qui aide l'utilisateur à
organiser ses repas de la semaine de façon **équilibrée** et **économique**.

L'utilisateur gère sa propre bibliothèque d'**ingrédients** et de **recettes**,
puis l'application **génère automatiquement** un menu hebdomadaire (7 jours × 3 repas)
adapté à son **budget**, au **nombre de convives** et à ses **préférences alimentaires**
(végétarien, vegan, sans porc…). L'application calcule le **coût total** et les
**apports nutritionnels** (calories, protéines), et permet d'**exporter** le menu
en PDF ou au format calendrier (ICS).

### Objectif pédagogique

Ce projet met en œuvre une application web **complète et sécurisée** :
- Architecture **MVC** (Modèle - Vue - Contrôleur) en PHP « vanilla » (sans framework)
- **API REST** consommée en **Ajax** (JavaScript `fetch`)
- Base de données **MySQL** relationnelle (clés étrangères, vues, procédures stockées)
- **Authentification** complète (inscription, connexion, vérification d'e-mail,
  réinitialisation de mot de passe)
- Bonnes pratiques de **sécurité** (CSRF, hachage des mots de passe, requêtes préparées, XSS)

---

## 2. Fonctionnalités

| Domaine | Fonctionnalités |
|---|---|
| **Compte** | Inscription, connexion, déconnexion, vérification d'e-mail, mot de passe oublié |
| **Profil** | Modification du profil, changement de mot de passe |
| **Ingrédients** | Ajouter / modifier / supprimer / rechercher ses ingrédients (prix, nutrition, catégorie) |
| **Recettes** | Créer / supprimer des recettes (type de repas, régime, ingrédients) |
| **Menus** | Génération automatique, sauvegarde, chargement, suppression |
| **Suivi** | Coût total / par repas / par personne, calories, protéines, alerte budget |
| **Export** | Export PDF (jsPDF) et ICS (calendrier) |
| **Favoris** | Marquer des recettes en favori, filtrer par type de repas |
| **Confort** | Mode clair / sombre / système, interface responsive (mobile, tablette, desktop) |

---

## 3. Stack technique

| Couche | Technologie |
|---|---|
| **Back-end** | PHP 8.x (sans framework), POO, namespaces, autoloader PSR-4 |
| **Base de données** | MySQL / MariaDB (moteur InnoDB, charset `utf8mb4`) |
| **Front-end** | HTML5, CSS3 (variables CSS), JavaScript (ES6, Ajax `fetch`) |
| **E-mails** | PHPMailer (SMTP Gmail) |
| **Export** | jsPDF (PDF), format ICS (calendrier) |
| **Polices** | Playfair Display + Commissioner (Google Fonts) |

---

## 4. Architecture

Le projet suit une architecture inspirée du **MVC** :

```
   Navigateur (HTML/CSS/JS)
          │  requêtes Ajax (fetch)
          ▼
   ┌──────────────┐      ┌──────────────────┐
   │  public/     │      │   api.php        │   ← points d'entrée
   │  index.php   │      │  (API REST JSON) │
   │ (routeur)    │      └────────┬─────────┘
   └──────┬───────┘               │
          │ sert les pages        │ route vers
          ▼                       ▼
   Vues (.php / .html)     ┌──────────────┐
                           │ Contrôleurs  │  (app/controllers)
                           └──────┬───────┘
                                  │ utilisent
                    ┌─────────────┼─────────────┐
                    ▼             ▼             ▼
              ┌─────────┐   ┌──────────┐  ┌──────────┐
              │ Modèles │   │ Services │  │   Core   │
              │ (BDD)   │   │ (logique)│  │ (outils) │
              └────┬────┘   └──────────┘  └──────────┘
                   │
                   ▼
              ┌─────────┐
              │  MySQL  │
              └─────────┘
```

### Les rôles

- **Routeur** (`public/index.php`) : front controller qui reçoit **toutes** les requêtes,
  sert les pages (HTML/PHP), les fichiers statiques (CSS/JS/images), et délègue `/api/*`.
- **Contrôleurs** (`app/controllers/`) : reçoivent la requête, vérifient l'authentification,
  appellent les modèles/services, renvoient une réponse JSON.
- **Modèles** (`app/models/`) : la seule couche qui parle à la base de données (requêtes SQL).
- **Services** (`app/services/`) : la logique métier pure (génération de menu, calculs
  nutritionnels, envoi d'e-mails).
- **Core** (`app/core/`) : outils transverses (connexion BDD, réponses API, sécurité).
- **Middleware** (`app/middleware/`) : contrôle d'accès (authentification, sessions).

---

## 5. Arborescence du projet

```
Projet/
├── DOCUMENTATION.md          ← ce fichier
├── index.html                Page d'accueil (landing, présentation publique)
├── index.php                 L'application (SPA : génération de menu, ingrédients…)
├── api.php                   Point d'entrée de l'API REST (JSON)
├── Contact.html              Page de contact + FAQ
├── PolitiqueDeConfidentialite.html
├── ConditionsDeUtilisation.html
│
├── user/                     ◄ Pages "côté utilisateur" (authentification)
│   ├── login.php             Connexion
│   ├── register.php          Inscription
│   ├── forgot-password.php   Demande de réinitialisation de mot de passe
│   ├── reset-password.php    Définition d'un nouveau mot de passe
│   └── verify-email.php      Confirmation d'adresse e-mail
│
├── public/
│   ├── index.php             ◄ Routeur central (front controller)
│   └── account.php           Espace compte (profil, paramètres, menus, favoris)
│
├── app/                      ◄ Cœur applicatif (back-end)
│   ├── controllers/          Contrôleurs (reçoivent les requêtes API)
│   │   ├── authController.php       Inscription / connexion / déconnexion
│   │   ├── AccountController.php     Profil / mot de passe / paramètres / favoris
│   │   ├── IngredientController.php  CRUD des ingrédients
│   │   ├── RecipeController.php      CRUD des recettes
│   │   └── MenuController.php        Menus hebdomadaires + génération
│   │
│   ├── models/               Accès aux données (SQL)
│   │   ├── User.php          Utilisateurs, settings, favoris, tokens
│   │   ├── Ingredient.php    Ingrédients
│   │   └── Meal.php          Recettes & menus
│   │
│   ├── services/             Logique métier
│   │   ├── MailService.php           Envoi d'e-mails (PHPMailer)
│   │   ├── mealGeneratorService.php  Génération algorithmique de menus
│   │   └── nutritionService.php      Calculs coûts & apports nutritionnels
│   │
│   ├── core/                 Outils transverses
│   │   ├── Api.php           Réponses JSON, lecture du corps, CSRF
│   │   ├── database.php      Connexion PDO (singleton)
│   │   └── Security.php      CSRF, hachage, validation, tokens
│   │
│   ├── middleware/
│   │   └── authMiddleware.php  Contrôle d'accès & gestion de session
│   │
│   └── models/utlisateurs/vendor/   PHPMailer (dépendance Composer)
│
├── assets/
│   ├── css/                  Feuilles de style
│   │   ├── roots.css         Variables CSS (couleurs, espacements…)
│   │   ├── style.css         Style de l'application
│   │   ├── auth.css          Style des pages de connexion/inscription
│   │   ├── index.css         Style de la landing page
│   │   ├── pages.css         Style des pages légales / contact
│   │   └── theme.css         Mode sombre + bouton de bascule
│   ├── js/
│   │   ├── app.js            Logique de l'application principale
│   │   ├── account.js        Logique de l'espace compte
│   │   ├── auth.js           Logique connexion/inscription
│   │   ├── api.js            Client Ajax centralisé (appels à api.php)
│   │   └── theme.js          Gestion du thème clair/sombre
│   └── img/                  Images (logo, fond, favicon)
│
├── config/
│   └── config.php            Configuration globale (BDD, SMTP, session, autoloader)
│
└── database/
    └── schema.sql            Structure de la base + données de démonstration
```

---

## 6. Base de données

Base : **`meal_planner`** (MySQL, InnoDB, `utf8mb4_unicode_ci`).

### Tables principales

| Table | Rôle |
|---|---|
| `users` | Comptes utilisateurs (+ `email_verified`) |
| `email_verifications` | Jetons de confirmation d'e-mail |
| `password_resets` | Jetons de réinitialisation de mot de passe |
| `user_settings` | Préférences par défaut (budget, personnes, régime) |
| `ingredients` | Bibliothèque d'ingrédients (prix, nutrition, catégorie) |
| `recipes` | Recettes (type de repas, régime, coût, nutrition) |
| `recipe_ingredients` | Ingrédients d'une recette (relation N-N) |
| `favorites` | Recettes favorites (relation utilisateur ↔ recette) |
| `weekly_menus` | Menus hebdomadaires sauvegardés |
| `menu_meals` | Les 21 créneaux d'un menu (7 jours × 3 repas) |
| `shopping_lists` | Listes de courses générées |

### Schéma relationnel (simplifié)

```
users ──┬──< ingredients
        ├──< recipes ──< recipe_ingredients
        ├──< favorites >── recipes
        ├──< weekly_menus ──< menu_meals >── recipes
        ├──< user_settings
        ├──< email_verifications
        └──< password_resets
```

### Atouts pédagogiques du schéma
- **Clés étrangères** avec `ON DELETE CASCADE` (suppression propre des données liées).
- **Vues** : `recipe_summary` (recette + ingrédients), `user_statistics` (compteurs par utilisateur).
- **Procédures stockées** : `generate_random_menu`, `generate_shopping_list`.
- **Compte « système »** (`user_id = 1`) qui porte des recettes/ingrédients par défaut,
  copiés vers chaque nouvel utilisateur à l'inscription.

---

## 7. Routage & API

### Le front controller (`public/index.php`)

Toutes les requêtes passent par le routeur, qui décide quoi servir :

1. **Fichier statique** (`.css`, `.js`, image…) → servi tel quel.
2. **`/api/...`** ou **`/api.php/...`** → délégué à l'API.
3. **Route nommée** (URL propre) → page correspondante. Exemples :
   - `/` → `index.html` (accueil)
   - `/login` (ou `/login.php`) → `user/login.php`
   - `/register`, `/forgot-password`, `/reset-password`, `/verify-email` → `user/…`
   - `/app` → `index.php` (l'application)
   - `/account` → `public/account.php`
4. **Accès direct** à un `.php`/`.html` existant.
5. Sinon → **404**.

### API REST (`api.php`)

Format : `api.php/<ressource>/<id?>/<action?>`. Réponses en **JSON**.

| Méthode | Route | Action |
|---|---|---|
| `POST` | `auth/login` | Connexion |
| `POST` | `auth/register` | Inscription |
| `POST` | `auth/logout` | Déconnexion |
| `GET` | `ingredients` | Liste des ingrédients |
| `POST` | `ingredients` | Créer un ingrédient |
| `PUT` | `ingredients/{id}` | Modifier |
| `DELETE` | `ingredients/{id}` | Supprimer |
| `GET` | `recipes` | Liste des recettes |
| `POST` | `recipes` | Créer une recette |
| `DELETE` | `recipes/{id}` | Supprimer |
| `GET` | `menus` | Liste des menus |
| `GET` | `menus/{id}` | Détail d'un menu |
| `POST` | `menus/generate` | Générer un menu |
| `POST` | `menus` | Sauvegarder un menu |
| `DELETE` | `menus/{id}` | Supprimer |
| `GET/PUT` | `account/profile` | Profil |
| `PUT` | `account/password` | Mot de passe |
| `GET/PUT` | `account/settings` | Paramètres |
| `GET/POST/DELETE` | `account/favorites` | Favoris |

> Le **client Ajax** (`assets/js/api.js`) centralise tous ces appels : il ajoute
> automatiquement le **token CSRF** et gère les erreurs.

---

## 8. Sécurité

Le projet applique plusieurs bonnes pratiques essentielles :

| Risque | Protection mise en place |
|---|---|
| **Injection SQL** | Requêtes **préparées** PDO partout (placeholders `?` / `:nom`) |
| **XSS** | Échappement HTML systématique (`htmlspecialchars`, `Security::sanitize`) |
| **CSRF** | Token CSRF généré en session, vérifié sur chaque action modifiante |
| **Mots de passe** | Hachage **Argon2id** (ou bcrypt en repli) — jamais stockés en clair |
| **Vol de session** | `session_regenerate_id` à la connexion (anti-fixation de session) |
| **Énumération de comptes** | Message identique que l'e-mail existe ou non (mot de passe oublié) |
| **Jetons (e-mail/reset)** | Stockés **hachés** (SHA-256), à **usage unique**, avec **expiration** |
| **Force du mot de passe** | 8 caractères min., 1 majuscule, 1 minuscule, 1 chiffre, 1 caractère spécial |

---

## 9. Système d'e-mails (PHPMailer)

Le service `app/services/MailService.php` centralise 3 envois :

1. **E-mail de bienvenue** — à l'inscription.
2. **E-mail de vérification** — lien unique pour confirmer l'adresse (valable 24 h).
3. **E-mail de réinitialisation** — lien « mot de passe oublié » (valable 1 h).

### Points clés
- Envoi via **SMTP Gmail** (configuré dans `config/config.php`).
- **Mode développement** : si aucun mot de passe SMTP n'est défini, l'e-mail est
  écrit dans les **logs PHP** au lieu d'être envoyé (l'application ne se bloque jamais).
- Les e-mails sont en **HTML** (avec version texte de secours) et utilisent les
  couleurs de la charte graphique.

> ⚠️ Gmail exige un **mot de passe d'application** (16 caractères), pas le mot de
> passe du compte. La validation en 2 étapes doit être activée.

---

## 10. Installation & configuration

### Prérequis
- PHP **8.x** (avec extensions `pdo_mysql`, `openssl`)
- MySQL **5.7+** / MariaDB
- (Optionnel) Composer pour PHPMailer — déjà inclus dans `vendor/`

### Étapes

1. **Créer la base de données** :
   ```bash
   mysql -u root < database/schema.sql
   ```

2. **Configurer** `config/config.php` :
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'meal_planner');
   define('DB_USER', 'root');
   define('DB_PASS', '');                       // votre mot de passe MySQL
   define('APP_URL', 'http://localhost:3000');  // l'URL de votre serveur

   // SMTP (mot de passe d'application Gmail, 16 caractères)
   define('MAIL_USERNAME', 'votre.compte@gmail.com');
   define('MAIL_PASSWORD', 'xxxxxxxxxxxxxxxx');
   define('MAIL_FROM_EMAIL', 'votre.compte@gmail.com');
   ```

3. **Lancer le serveur** (le routeur `public/index.php` gère tous les chemins) :
   ```bash
   php -S localhost:3000 -t . public/index.php
   ```

4. Ouvrir **http://localhost:3000** dans le navigateur.

---

## 11. Guide d'utilisation

1. **Accueil** (`/`) : page de présentation → « Commencer gratuitement ».
2. **Inscription** (`/register`) : créer un compte → e-mails de bienvenue + vérification.
3. **Connexion** (`/login`) : se connecter (lien « Mot de passe oublié ? » disponible).
4. **Application** (`/app`) :
   - Onglet **Générer un menu** : budget + personnes + régime → bouton de génération.
   - Onglet **Mes ingrédients** : gérer sa bibliothèque d'ingrédients.
   - Onglet **Mes recettes** : créer et filtrer ses recettes.
   - Onglet **Mes menus** : sauvegarder, ajouter aux favoris, exporter (PDF/ICS).
5. **Mon compte** (`/account`) : profil, mot de passe, paramètres, menus sauvegardés, favoris.
6. **Thème** : bouton 🌙/☀️ pour basculer clair/sombre (mémorisé sur l'appareil).

---

## 12. Parcours du code

Pour comprendre le projet, voici un ordre de lecture conseillé :

1. **`config/config.php`** — comment tout démarre (BDD, session, autoloader).
2. **`public/index.php`** — comment une requête est routée.
3. **`api.php`** — comment l'API dispatche vers les contrôleurs.
4. **`app/core/`** — les outils de base (`Database`, `Api`, `Security`).
5. **`app/middleware/authMiddleware.php`** — comment l'authentification protège les routes.
6. **Un contrôleur simple** : `IngredientController.php` (le pattern CRUD typique).
7. **Un modèle** : `Ingredient.php` (les requêtes préparées).
8. **La génération de menu** : `MenuController.php` + `mealGeneratorService.php`.
9. **Le front** : `assets/js/api.js` (client Ajax) puis `assets/js/app.js`.

> 💡 **Tout le code est commenté** au niveau « développeur en formation » :
> chaque classe, fonction et passage non évident est expliqué en français.

---

*© 2026 Planificateur de Repas — Dibaj NASRULLAH & JJ. Tous droits réservés.*
