# Déploiement en production

Ce document décrit la mise en ligne de `mosltrans.fr` sur un serveur mutualisé ou un VPS.
Pour l'installation sur un poste de développement, voir [`INSTALLATION.md`](INSTALLATION.md).

---

## Prérequis côté serveur

| Élément | Version attendue | Vérification |
|---|---|---|
| PHP | 8.2 minimum (contrainte `composer.json`) | `php -v` |
| Extensions PHP | `ctype`, `iconv`, `pdo_sqlite`, `mbstring`, `intl` | `php -m` |
| Composer | 2.x | `composer --version` |
| Serveur web | Apache avec `mod_rewrite`, ou Nginx | — |
| Accès | SSH ou SFTP, et droits d'écriture sur `var/` | — |

La base étant un fichier SQLite, **aucun serveur de base de données n'est à installer**.
C'était la contrainte du client inscrite dans la convention de stage.

---

## 1. Transférer les fichiers

Transférer le projet **sans** les dossiers suivants, qui sont régénérés sur place :

```
vendor/          dépendances, réinstallées par Composer
var/cache/       cache applicatif
var/log/         journaux
node_modules/    le cas échéant
.git/            historique de versions
.env.local       secrets du poste de développement
```

En SSH, depuis le poste de développement :

```bash
rsync -av --exclude vendor --exclude var/cache --exclude var/log \
          --exclude .git --exclude .env.local \
          ./ utilisateur@serveur:/var/www/mosltrans/
```

---

## 2. Installer les dépendances de production

```bash
cd /var/www/mosltrans
composer install --no-dev --optimize-autoloader
```

- `--no-dev` écarte les paquets de développement (PHPUnit, profileur, maker).
  Ils ne doivent **jamais** se trouver en production : le profileur Symfony expose
  la configuration, les requêtes SQL et les variables d'environnement.
- `--optimize-autoloader` génère une table de classes statique : le chargement
  est plus rapide qu'une recherche de fichiers à chaque requête.

---

## 3. Créer le fichier d'environnement de production

Créer `/var/www/mosltrans/.env.local` **sur le serveur uniquement**. Ce fichier
n'est pas versionné : il figure dans `.gitignore`, comme indiqué dans `SECURITY.md`.

```dotenv
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<valeur générée, 32 octets en hexadécimal>

DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"

MAILER_DSN=smtp://utilisateur:motdepasse@smtp.hebergeur.fr:587
MAILER_FROM=contact@mosltrans.fr

DEFAULT_URI=https://www.mosltrans.fr
```

Générer une clé secrète propre au serveur :

```bash
php -r 'echo bin2hex(random_bytes(16)), PHP_EOL;'
```

**Points de vigilance**

- `APP_ENV=prod` et `APP_DEBUG=0` désactivent la barre de débogage et les pages
  d'erreur détaillées. Laisser `dev` en production revient à publier la structure
  interne de l'application.
- La clé `APP_SECRET` doit être **différente** de celle du poste de développement :
  elle signe les jetons CSRF et les cookies de session.
- `MAILER_DSN=null://null` n'envoie aucun e-mail. Le remplacer par le SMTP réel,
  sinon les confirmations de contact ne partiront pas.
- `DEFAULT_URI` sert à générer des URL absolues dans les e-mails.

---

## 4. Préparer la base de données

```bash
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

Puis créer le premier compte administrateur :

```bash
php bin/console app:create-admin --env=prod
```

Enfin, protéger le fichier de base :

```bash
chmod 660 var/data.db
chown www-data:www-data var/data.db
```

Le fichier `var/data.db` se trouve **hors** du dossier `public/`, il n'est donc pas
téléchargeable depuis le web. C'est volontaire : un fichier SQLite placé dans la
racine web serait téléchargeable par n'importe qui.

---

## 5. Compiler les assets

```bash
php bin/console tailwind:build --minify --env=prod
php bin/console asset-map:compile --env=prod
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

- `tailwind:build --minify` produit la feuille de style compressée.
- `asset-map:compile` copie les fichiers d'`assets/` vers `public/assets/` avec une
  empreinte dans leur nom : le navigateur peut les garder en cache indéfiniment,
  et récupère la nouvelle version dès qu'un fichier change.
- `cache:warmup` prépare le conteneur pour que la première visite ne soit pas lente.

---

## 6. Droits sur les dossiers

```bash
chown -R www-data:www-data var/
chmod -R 775 var/
```

Seul `var/` doit être accessible en écriture. Le reste du projet peut rester en
lecture seule pour le serveur web.

---

## 7. Configurer le serveur web

La racine du site doit pointer sur **`public/`**, jamais sur la racine du projet.
Sinon `.env.local`, `var/data.db`, `src/` et `vendor/` deviennent accessibles depuis
un navigateur.

### Apache

```apache
<VirtualHost *:443>
    ServerName www.mosltrans.fr
    DocumentRoot /var/www/mosltrans/public

    <Directory /var/www/mosltrans/public>
        AllowOverride None
        Require all granted
        FallbackResource /index.php
    </Directory>

    <Directory /var/www/mosltrans>
        Options -Indexes
    </Directory>

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/mosltrans.fr/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/mosltrans.fr/privkey.pem

    ErrorLog  /var/log/apache2/mosltrans_error.log
    CustomLog /var/log/apache2/mosltrans_access.log combined
</VirtualHost>
```

Sur un hébergement mutualisé sans accès à la configuration, le paquet
`symfony/apache-pack` fournit un `.htaccess` à placer dans `public/` :

```bash
composer require symfony/apache-pack
```

### Nginx

```nginx
server {
    listen 443 ssl;
    server_name www.mosltrans.fr;
    root /var/www/mosltrans/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ { return 404; }

    error_log  /var/log/nginx/mosltrans_error.log;
    access_log /var/log/nginx/mosltrans_access.log;
}
```

---

## 8. Activer HTTPS

```bash
certbot --apache -d mosltrans.fr -d www.mosltrans.fr
```

Le chiffrement est indispensable : le formulaire de connexion transmet un mot de
passe, et le formulaire de contact des données personnelles. Sans HTTPS, les deux
circulent en clair.

Rediriger ensuite le trafic HTTP vers HTTPS et vérifier le renouvellement
automatique du certificat :

```bash
certbot renew --dry-run
```

---

## 9. Vérifications après mise en ligne

| À vérifier | Comment | Résultat attendu |
|---|---|---|
| Le site répond | ouvrir `https://www.mosltrans.fr` | page d'accueil complète, avec les styles |
| Pas de mode debug | ajouter `/_profiler` à l'URL | erreur 404 |
| Fichiers protégés | ouvrir `/.env.local` puis `/var/data.db` | erreur 404 ou 403 |
| Formulaire de contact | envoyer un message réel | redirection vers `/contact/merci` |
| E-mail de confirmation | consulter la boîte de réception | message reçu |
| Enregistrement en base | ouvrir `/admin` | la demande figure dans la liste |
| Authentification | tenter `/admin` sans être connectée | redirection vers `/login` |
| Journaux | `tail -f var/log/prod.log` | aucune erreur |
| Affichage mobile | ouvrir depuis un téléphone | menu burger fonctionnel |

---

## 10. Mettre à jour une version déjà en ligne

```bash
cd /var/www/mosltrans
php bin/console app:maintenance on        # si une page de maintenance existe

git pull origin main
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console tailwind:build --minify --env=prod
php bin/console asset-map:compile --env=prod
php bin/console cache:clear --env=prod

php bin/console app:maintenance off
```

**Sauvegarder la base avant toute migration** :

```bash
cp var/data.db var/sauvegardes/data-$(date +%F-%H%M).db
```

SQLite étant un fichier unique, une sauvegarde est une simple copie. C'est l'un des
avantages de ce choix technique, à mettre en avant.

---

## 11. Script de déploiement

Fichier `bin/deploy.sh`, à rendre exécutable par `chmod +x bin/deploy.sh` :

```bash
#!/usr/bin/env bash
# Déploiement de mosltrans.fr en production.
# Usage : ./bin/deploy.sh
set -euo pipefail

PROJET="/var/www/mosltrans"
HORODATAGE=$(date +%F-%H%M)

cd "$PROJET"

echo "→ Sauvegarde de la base"
mkdir -p var/sauvegardes
cp var/data.db "var/sauvegardes/data-$HORODATAGE.db"

echo "→ Récupération du code"
git pull origin main

echo "→ Dépendances de production"
composer install --no-dev --optimize-autoloader

echo "→ Migrations"
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

echo "→ Compilation des assets"
php bin/console tailwind:build --minify --env=prod
php bin/console asset-map:compile --env=prod

echo "→ Cache"
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

echo "→ Droits"
chown -R www-data:www-data var/
chmod -R 775 var/

echo "Déploiement terminé le $HORODATAGE"
```

---

## 12. En cas de problème

| Symptôme | Cause probable | Correction |
|---|---|---|
| Erreur 500 sans détail | `APP_ENV=prod` masque l'erreur | lire `var/log/prod.log` |
| Page sans style | assets non compilés | `tailwind:build --minify` puis `asset-map:compile` |
| `Unable to write in the cache directory` | droits insuffisants | `chown -R www-data:www-data var/` |
| `SQLSTATE[HY000]: unable to open database file` | droits sur `var/data.db` | `chmod 660` et vérifier le propriétaire |
| Aucun e-mail envoyé | `MAILER_DSN` resté sur `null://null` | renseigner le SMTP de l'hébergeur |
| Tout le site renvoie 404 | racine web mal placée | la faire pointer sur `public/` |

**Revenir à la version précédente** :

```bash
git checkout <dernier-commit-stable>
composer install --no-dev --optimize-autoloader
cp var/sauvegardes/data-<horodatage>.db var/data.db
php bin/console cache:clear --env=prod
```

---

## Ce que cette procédure démontre

Compétence **C8 — Documenter le déploiement d'une application dynamique web ou web mobile** :

- la procédure est rédigée et tient compte des dépendances et des versions ;
- le script `bin/deploy.sh` est écrit et commenté ;
- les environnements sont distingués : développement, test et production ;
- la sécurité du déploiement est traitée : secrets hors du dépôt, racine web sur
  `public/`, base hors de la racine web, HTTPS, profileur désactivé.
