# Forgab – Réseau Social

Plateforme complète de réseau social moderne, sécurisée et réactive développée en **PHP 8**, **PostgreSQL** (optimisé pour **Supabase**), **JavaScript Vanilla**, **WebRTC (PeerJS)** et **PWA**.

---

## Sommaire

1. [Vue d'ensemble](#vue-densemble)
2. [Fonctionnalités](#fonctionnalités)
3. [Architecture du projet](#architecture-du-projet)
4. [Schéma de base de données](#schéma-de-base-de-données)
5. [Sécurité](#sécurité)
6. [Prérequis et Installation locale](#prérequis-et-installation-locale)
7. [Configuration des environnements](#configuration-des-environnements)
8. [Déploiement en production (Railway / Supabase)](#déploiement-en-production-railway--supabase)
9. [Maintenance et Tâches planifiées (Cron)](#maintenance-et-tâches-planifiées-cron)

---

## Vue d'ensemble

**Forgab** est un réseau social complet aligné sur les standards actuels de **Facebook (2026)** :
- **Design System Facebook moderne** : Thèmes Clair et Sombre (Dark Mode) natifs avec persistance dans le `localStorage` sans scintillement.
- **Top Navigation Bar Facebook** : Logo officiel, barre de recherche interactive, 5 onglets centraux avec indicateur actif, notifications avec popover et badge en temps réel, menu profil.
- **Disposition 3 colonnes** :
  - *Gauche* : Raccourcis de navigation (Profil, Amis, Vidéos, Enregistrements, Sécurité 2FA, Dark Mode toggle).
  - *Centre* : Carrousel de stories, boîte de publication enrichie et fil d'actualité infini.
  - *Droite* : Demandes d'amis interactives (actions AJAX 1-clic) et liste des contacts connectés.
- **Stories & Reels (format 9:16)** : Carrousel avec téléversement immédiat et visionneuse plein écran avec barre de progression dynamique.
- **Compositeur de publication avancé** : Modale Facebook avec sélecteur d'audience (Public/Amis/Privé), arrière-plans dégradés colorés, sélecteur d'humeurs/activités et prévisualisation multimédia.
- **Publications & Réactions Facebook** : Menu flottant de 7 réactions animées au survol (👍 Like, ❤️ Love, 😂 Haha, 😮 Wow, 😢 Sad, 😡 Angry, 👎 Dislike), formatage des hashtags/mentions, commentaires interactifs et partages.
- **Dock Messenger Flottant** : Fenêtre de discussion en direct ancrée en bas à droite de l'écran permettant d'échanger des messages et de lancer des appels audio/vidéo sans quitter le fil d'actualité.
- **Appels audio et vidéo P2P** : Communications chiffrées de bout en bout via WebRTC (PeerJS).
- **Administration & PWA** : Tableau de bord de modération, gestion des rôles et support Progressive Web App installable.

---

## Fonctionnalités

### 1. Authentification & Sécurité du compte
- **Inscription complète** : validation stricte du mot de passe (longueur minimale, majuscules, chiffres, caractères spéciaux), téléversement de photo de profil avec vérification MIME stricte.
- **Validation par email** : envoi d'un code OTP à 6 chiffres via PHPMailer (ou journalisation locale `mail_dev.log` en développement).
- **Authentification double facteur (2FA TOTP)** : implémentation native du standard RFC 6238 dans `totp.php`, génération de QR code dynamique côté client compatible avec Google Authenticator et Authy.
- **Protection anti force-brute** : limitation du taux de tentatives de connexion (rate limiting) par adresse IP et adresse email dans la table `login_attempts`.

### 2. Fil d'actualité & Publications
- **Publications enrichies** : publication de messages texte, photographies et vidéos (MP4, WebP, etc.).
- **Flux infini (Infinite Scroll)** : chargement asynchrone des posts par `IntersectionObserver` via l'API REST `api_posts.php`.
- **Performance SQL** : élimination du problème N+1 grâce aux clauses d'agrégation `COUNT(pl.id) FILTER (WHERE pl.type = ...)` exécutées en une seule requête SQL.
- **7 Réactions dynamiques** : Like (👍), Love (❤️), Haha (😂), Wow (😮), Triste (😢), En colère (😡), Dislike (👎) avec sélecteur interactif au survol et basculement instantané.
- **Commentaires et partages** : ajout et consultation de commentaires en direct, repartage de publications sur son propre profil.

### 3. Stories éphémères (24h)
- Téléversement rapide de photo/vidéo depuis la barre de stories de l'accueil.
- Expiration automatique des contenus au bout de 24 heures (`expires_at = NOW() + INTERVAL '24 hours'`).
- Visionneuse modale intégrée permettant d'afficher les stories des amis en plein écran.

### 4. Messagerie instantanée & Appels WebRTC
- **Chat en direct** : flux de messages bidirectionnel sans rechargement de page, alimenté par Server-Sent Events (`sse_chat.php`).
- **Pièces jointes multimédias** : envoi d'images, vidéos, mémos audio et documents dans la conversation.
- **Protection IDOR** : vérification stricte côté serveur que l'utilisateur connecté fait bien partie de la conversation et est ami avec le destinataire.
- **Appels audio & vidéo P2P** : intégration de WebRTC via PeerJS (`call.php`) pour des communications audio/vidéo chiffrées de bout en bout et sans intermédiaire.

### 5. Réseau de membres & Profils
- Annuaire des utilisateurs avec recherche en direct insensible à la casse (PostgreSQL `ILIKE`).
- Gestion des relations d'amitié : envoi de demande, acceptation, refus et statut en attente.
- Page de profil complète (`profile.php`) et formulaire d'édition de profil (`edit_profile.php`) permettant de mettre à jour le nom, prénom, numéro de contact, biographie et photo.

### 6. Administration & Modération
- Système de permissions hiérarchique : `user` (0), `moderateur` (1), `admin` (2).
- Tableau de bord (`admin.php`) réservé aux administrateurs :
  - Modification des rôles des utilisateurs.
  - Bannissement / suspension de comptes non conformes.
  - Suppression modératrice de posts et nettoyage automatique des médias associés.
  - Consultation des logs d'audit d'activité (`audit_logs`).

### 7. PWA (Progressive Web App)
- `manifest.json` configuré pour l'installation sur smartphones et ordinateurs.
- Service Worker (`sw.js`) gérant le cache des ressources statiques et la résilience hors-ligne.
- Icônes adaptatives 192x192 et 512x512.

---

## Architecture du projet

```
r-seau_social/
├── .env.example              # Gabarit des variables d'environnement
├── .gitignore                # Fichiers exclus du versionnement Git
├── README.md                 # Documentation technique du projet
├── composer.json             # Dépendances PHP (PHPMailer)
├── composer.lock             # Verrou des versions des dépendances
├── config.php                # Connexion centralisée PDO PostgreSQL (Supabase)
├── router.php                # Routeur pour le serveur de développement PHP intégré
├── railway.json              # Configuration de déploiement automatique sur Railway
├── setup_php.bat             # Script de démarrage rapide local sous Windows
│
├── security.php              # En-têtes HTTP, Content-Security-Policy et protection CSRF
├── functions.php             # Fonctions utilitaires métier (relations, requêtes)
├── storage.php               # Gestionnaire d'upload local et abstraction CDN/S3
├── audit.php                 # Journalisation des audits et contrôle de débit (rate limiting)
├── roles.php                 # Définition des permissions et contrôle des rôles
│
├── index.php                 # Point d'entrée avec redirection automatique
├── login.php                 # Page de connexion avec rate limiting et redirection 2FA
├── logout.php                # Déconnexion et destruction propre de la session
├── register.php              # Formulaire et traitement d'inscription utilisateur
├── verify.php                # Validation du code OTP envoyé par email
├── totp.php                  # Classe autonome de calcul et vérification RFC 6238 TOTP
├── totp_setup.php            # Activation / désactivation du double facteur 2FA
├── totp_verify.php           # Écran de saisie du code 2FA lors de la connexion
│
├── accueil.php               # Tableau de bord principal, stories, publications et flux
├── feed.php                  # Vue alternative classique du fil d'actualité
├── api_posts.php             # API JSON pour la pagination infinie des publications
├── ajax_post.php             # Endpoint AJAX de création de post
├── ajax_delete_post.php      # Endpoint AJAX de suppression modératrice de post
├── ajax_like.php             # Endpoint AJAX de gestion des 7 réactions
├── ajax_comment.php          # Endpoint AJAX de création et lecture des commentaires
├── ajax_share.php            # Endpoint AJAX de repartage de post
├── ajax_story.php            # Endpoint AJAX de publication de story
│
├── users.php                 # Annuaire des membres et gestion des demandes d'amis
├── friend_request.php        # Traitement des demandes d'amis (demande, acceptation, refus)
├── profile.php               # Affichage d'un profil utilisateur public ou privé
├── edit_profile.php          # Formulaire de modification du profil et de la photo
│
├── chat.php                  # Interface de messagerie instantanée
├── ajax_chat.php             # Envoi et historique des messages de chat
├── sse_chat.php              # Flux SSE en temps réel pour la messagerie
├── call.php                  # Interface d'appel audio/vidéo WebRTC PeerJS
├── save_peerjs_id.php        # Sauvegarde de l'identifiant PeerJS de l'utilisateur
│
├── notifications.php         # Page récapitulative des notifications de l'utilisateur
├── sse_notifications.php     # Flux SSE en temps réel pour les notifications toast
├── admin.php                 # Console d'administration et de modération
├── cron_cleanup.php          # Script de nettoyage périodique (stories, logs, sessions)
├── send_mail.php             # Service d'envoi d'emails via PHPMailer
│
├── manifest.json             # Manifeste PWA
├── sw.js                     # Service Worker PWA
├── style.css                 # Feuille de style du feed
├── profile-card.css          # Feuille de style de la carte de profil
├── site-logo.png             # Logo de la plateforme
├── default-user.png          # Avatar par défaut des profils
│
├── icons/                    # Icônes de l'application PWA (192px, 512px)
├── uploads/                  # Dossier des fichiers téléversés (posts, avatars, stories)
└── supabase/
    └── migrations/           # Scripts SQL d'initialisation pour Supabase
```

---

## Schéma de base de données

L'application est **100% multi-bases** : elle fonctionne aussi bien sur **MySQL / MariaDB (XAMPP)** en local que sur **PostgreSQL (Supabase / Production)** :
- **Pour XAMPP (MySQL)** : importez le fichier `schema_mysql.sql` (ou exécutez `php init_mysql.php`).
- **Pour Supabase (PostgreSQL)** : exécutez le fichier `supabase_schema.sql`.

### Tables principales :
- **`users`** : identifiant, nom, prénom, email, mot de passe hashé (`BCRYPT`), contact, sexe, date de naissance, adresse, bio, photo, statut de vérification (`is_verified`), secret TOTP (`totp_secret`, `totp_enabled`), rôle (`user`, `moderateur`, `admin`), `peerjs_id`.
- **`posts`** : publication (`content`, `media`), référence à l'auteur (`user_id`), horodatage.
- **`post_likes`** : réactions associées aux publications (`like`, `love`, `haha`, `wow`, `sad`, `angry`, `dislike`) avec contrainte d'unicité `(post_id, user_id)`.
- **`comments`** : commentaires liés à un post avec clé étrangère en cascade.
- **`friends`** : relations entre deux utilisateurs (`from_user`, `to_user`, `status` valant `pending` ou `accepted`).
- **`messages`** : messages privés (`sender_id`, `receiver_id`, `content`, `file`, `file_type`, `created_at`).
- **`stories`** : médias éphémères avec horodatage de publication et d'expiration (`expires_at`).
- **`notifications`** : notifications destinées à un utilisateur (`content`, `link`, `is_read`, `created_at`).
- **`login_attempts`** : suivi des tentatives infructueuses de connexion pour le rate limiting.
- **`audit_logs`** : journal d'audit de sécurité des actions critiques (`user_id`, `action`, `target_type`, `target_id`, `ip`, `user_agent`).

---

## Sécurité

Le projet applique les principes de défense en profondeur :
1. **Protection contre le Cross-Site Scripting (XSS)** :
   - Échappement systématique des sorties via `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`.
   - Content-Security-Policy (CSP) stricte autorisant uniquement les sources nécessaires (`self`, `cdn.jsdelivr.net`, `unpkg.com`, `fonts.googleapis.com`, WebSockets PeerJS).
2. **Protection contre les failles CSRF** :
   - Jetons de synchronisation CSRF générés par `csrf_token()` et contrôlés systématiquement sur chaque requête POST via `csrf_verify()`.
3. **Protection contre les attaques par force-brute** :
   - Contrôle du taux de requêtes (`is_rate_limited()`) sur l'adresse IP et l'email avec blocage temporaire d'une heure après 5 tentatives échouées.
4. **Sessions durcies** :
   - Drapeaux `cookie_httponly = 1`, `cookie_samesite = Strict`, `use_strict_mode = 1`.
   - Régénération de l'identifiant de session (`session_regenerate_id(true)`) dès la validation de la connexion.
5. **Gestion de la concurrence SSE** :
   - Fermeture anticipée du verrou de session (`session_write_close()`) sur les flux d'événements continus (`sse_chat.php`, `sse_notifications.php`) pour éviter tout blocage des requêtes simultanées de l'utilisateur.
6. **Sécurisation des téléversements de fichiers** :
   - Analyse du contenu MIME réel (`finfo(FILEINFO_MIME_TYPE)`), renommage aléatoire des fichiers (`bin2hex(random_bytes(12))`), et restriction des extensions exécutables.
7. **Contrôle d'accès et IDOR** :
   - Vérification de l'appartenance des utilisateurs aux conversations avant toute restitution ou envoi de message.
   - Contrôle strict des rôles (`require_role()`) sur l'espace d'administration.

---

## Prérequis et Installation locale

### Prérequis
- **PHP 8.2 ou supérieur** avec les extensions :
  - `pdo_pgsql` (ou `pdo_mysql`)
  - `fileinfo`
  - `pdo_mysql` (pour XAMPP) et `pdo_pgsql` (pour Supabase)
  - `fileinfo`, `openssl`, `mbstring`
- **XAMPP** (avec module MySQL démarré) OU un projet **Supabase** (PostgreSQL).

### 1. Démarrage de MySQL sous XAMPP
1. Lancez le **XAMPP Control Panel**.
2. Cliquez sur le bouton **Start** en face du module **MySQL** (le voyant devient vert sur le port 3306).

### 2. Initialisation automatique de la base locale
Dans votre terminal (ou depuis le dossier du projet) :
```cmd
php init_mysql.php
```
Ce script crée automatiquement la base `reseau_social`, l'ensemble des tables depuis `schema_mysql.sql` et un compte administrateur prêt à l'emploi :
- **Email** : `admin@forgab.com`
- **Mot de passe** : `Admin1234!`

*(Alternative manuelle : importez le fichier `schema_mysql.sql` dans [phpMyAdmin](http://localhost/phpmyadmin)).*

### 3. Démarrage de l'application
- **Sous Windows** : double-cliquez sur `setup_php.bat`
- **Ou en ligne de commande** :
  ```cmd
  php -S localhost:8080 router.php
  ```
Ouvrez ensuite : **[http://localhost:8080/](http://localhost:8080/)**

---

## Configuration des environnements

Le fichier `config.php` supporte deux modes d'alimentation des identifiants :

1. **Variables d'environnement (Production - Railway / Render / Docker)** :
   Si la variable `DB_HOST` est présente dans l'environnement du système, `config.php` l'utilise en priorité :
   - `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
   - `SMTP_USER`, `SMTP_PASS`, `SMTP_HOST`, `SMTP_PORT`
   - `CRON_SECRET`

2. **Fichier de configuration local (`secrets.php`)** :
   En l'absence de variables d'environnement système, `config.php` charge `secrets.php`. Ce fichier est ignoré par Git (`.gitignore`) pour protéger vos données confidentielles.

---

## Déploiement en production (Railway / Supabase)

### Déploiement sur Railway :
1. Créez un nouveau projet sur [Railway.app](https://railway.app).
2. Liez votre dépôt GitHub `r-seau_social`.
3. Ajoutez les variables d'environnement dans les paramètres de votre service :
   ```env
   DB_HOST=db.xxxxxxxxxxxx.supabase.co
   DB_PORT=5432
   DB_NAME=postgres
   DB_USER=postgres
   DB_PASS=VotreMotDePasse
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_USER=votre-email@gmail.com
   SMTP_PASS=votre-mot-de-passe-application
   CRON_SECRET=une-cle-secrete-aleatoire
   ```
4. Le fichier `railway.json` configure automatiquement le démarrage avec `php -S 0.0.0.0:$PORT router.php`.

---

## Maintenance et Tâches planifiées (Cron)

Le fichier `cron_cleanup.php` permet d'automatiser l'entretien de la plateforme :
- Suppression des **stories périmées** de plus de 24 heures et de leurs fichiers médias.
- Purge des anciennes tentatives de connexion expirées (`login_attempts` > 2 heures).
- Nettoyage des logs d'audit datant de plus de 90 jours.

### Exécution du script :
- **En ligne de commande (CLI)** :
  ```bash
  php cron_cleanup.php
  ```
- **Par requête HTTP sécurisée** (via un service comme cron-job.org ou un webhook GitHub Actions) :
  ```
  GET https://votre-domaine.com/cron_cleanup.php?key=VOTRE_CRON_SECRET
  ```
  *(Ou en fournissant l'en-tête HTTP `X-Cron-Secret: VOTRE_CRON_SECRET`)*

---

## Auteurs & Licence

- **Projet** : Forgab – Réseau Social
- **Auteur** : jackAntonio
- **Licence** : Tous droits réservés.
