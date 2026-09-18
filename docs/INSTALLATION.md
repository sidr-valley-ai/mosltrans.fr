# Installation et tests en local

Ce guide couvre la mise en place d'un environnement local pour lancer et tester
le site (formulaire de contact, auth admin, back-office leads).

## Prérequis

- PHP >= 8.2 (le projet a été testé avec 8.3 et 8.4)
- Composer 2.x
- Extensions PHP : `mbstring`, `intl`, `pdo_sqlite`, `sqlite3`, `openssl`,
  `fileinfo`, `curl`, `zip`

Le projet utilise SQLite (fichier unique, pas de serveur DB à installer) et
Tailwind via AssetMapper (pas de Node/npm requis).

## Installation de PHP + Composer sur Windows

Si PHP n'est pas déjà installé :

```powershell
winget install --id PHP.PHP.8.4
```

Composer n'est pas disponible sur `winget` sous ce nom : télécharger le
`.phar` officiel et créer un wrapper.

```powershell
$toolsDir = "$env:LOCALAPPDATA\Programs\composer"
New-Item -ItemType Directory -Force -Path $toolsDir
Invoke-WebRequest -Uri "https://getcomposer.org/composer-stable.phar" -OutFile "$toolsDir\composer.phar"
```

Puis créer deux wrappers dans `$toolsDir` (un par type de terminal, `php.exe`
étant à adapter au chemin réel de l'installation PHP) :

- `composer.bat` (pour PowerShell/cmd) :
  ```bat
  @echo off
  "<chemin-vers-php.exe>" "<chemin-vers-composer.phar>" %*
  ```
- `composer` sans extension (pour Git Bash/MINGW64, qui ne résout pas les
  `.bat` par résolution d'extension comme le fait Windows) :
  ```sh
  #!/bin/sh
  exec "<chemin-vers-php.exe>" "<chemin-vers-composer.phar>" "$@"
  ```
  (`chmod +x` sur ce fichier)

Ajouter `$toolsDir` et le dossier d'installation de PHP au `PATH` utilisateur.

### php.ini

Les archives PHP Windows ne fournissent pas de `php.ini` actif par défaut.
Copier `php.ini-development` en `php.ini` dans le dossier d'installation de
PHP, puis activer (décommenter `;extension=xxx` → `extension=xxx`) :
`mbstring`, `intl`, `pdo_sqlite`, `sqlite3`, `openssl`, `fileinfo`, `curl`,
`zip`, et décommenter `extension_dir = "ext"` (le `php.ini` fourni pointe par
défaut vers `C:\php\ext`, qui n'existe pas si PHP est installé ailleurs).

### ⚠️ Piège fréquent : PATH pas pris en compte

Un terminal (PowerShell, Git Bash, **ou VS Code entier**) déjà ouvert avant
l'installation ne voit pas le nouveau `PATH` — il faut **fermer et rouvrir**
le terminal, ou **redémarrer complètement VS Code** (pas juste un nouvel
onglet de terminal : le terminal intégré hérite de l'environnement du
processus VS Code lui-même). Vérifier avec `php -v` et `composer --version`
dans un terminal fraîchement ouvert avant d'aller plus loin.

## Mise en place du projet

```bash
composer install
```

Générer un secret local (`.env.local` n'est pas versionné) :

```bash
php -r "echo 'APP_SECRET=' . bin2hex(random_bytes(16)) . PHP_EOL;" >> .env.local
```

Créer la base SQLite et appliquer les migrations :

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

Créer un compte administrateur (commande interactive : email + mot de passe
saisis à la main, jamais en argument de ligne de commande) :

```bash
php bin/console app:create-admin
```

Compiler le CSS Tailwind (la première exécution télécharge automatiquement le
binaire Tailwind, ~100 Mo) :

```bash
php bin/console tailwind:build
```

## Lancer le serveur

```bash
php -S 127.0.0.1:8080 -t public
```

> Le port `8000` par défaut de Symfony peut déjà être pris par un autre
> projet en cours (ex. Fretexia) — adapter le port au besoin.

En développement, préférer laisser Tailwind tourner en watch dans un terminal
séparé pour que le CSS se recompile à chaque modification des classes :

```bash
php bin/console tailwind:build --watch
```

## Parcours de test manuel (issue #20)

1. `http://127.0.0.1:8080/` — formulaire de contact, vérifier l'écriture en
   base d'un lead.
2. `http://127.0.0.1:8080/admin` sans être connecté — doit rediriger vers
   `/login` (protection `ROLE_ADMIN`).
3. `http://127.0.0.1:8080/login` — connexion avec le compte créé.
4. `http://127.0.0.1:8080/admin` — liste des leads (EasyAdmin), voir le lead
   du formulaire de contact.
5. Changer le statut d'un lead.
6. Se déconnecter, puis re-tenter `/admin` — doit redemander une connexion.

## Dépannage

| Symptôme | Cause probable |
|---|---|
| `php`/`composer` introuvable après installation | Terminal ouvert avant la mise à jour du PATH — le rouvrir (ou redémarrer VS Code entièrement) |
| `composer: command not found` dans Git Bash alors que `composer.bat` existe | Git Bash ne résout pas les extensions `.bat`/`.cmd` comme Windows — utiliser le wrapper `composer` sans extension |
| `Unable to load dynamic library 'xxx'` au démarrage de PHP | `extension_dir` du `php.ini` pointe vers un chemin qui n'existe pas (souvent `C:\php\ext` par défaut) — le corriger vers le dossier `ext` réel |
| `Warning: The lock file is not up to date` lors de `composer install` | Sans conséquence bloquante ; peut arriver après un merge manuel de `composer.lock` (conflit résolu à la main faute de Composer disponible). Lancer `composer update --lock` pour rafraîchir le hash si besoin |
| Erreur 500 `Built Tailwind CSS file does not exist` | Le CSS Tailwind n'a jamais été compilé — lancer `php bin/console tailwind:build` (voir « Mise en place du projet ») |
| Erreur 500 sur `/admin` après un `composer install`/`cache:clear`, alors que la config semble correcte | Le serveur `php -S` est un **process unique et persistant** : il garde le conteneur Symfony compilé en mémoire et ne relit pas les fichiers de `var/cache/` tant qu'il tourne. `php bin/console cache:clear` régénère bien le cache sur disque, mais il faut **arrêter puis relancer** `php -S 127.0.0.1:8080 -t public` pour que le serveur reprenne le nouveau conteneur |
