# Cas de débogage — mosltrans.fr

Ce document est un recueil pédagogique de bugs réels rencontrés et corrigés
pendant la mise en route locale du projet. Contrairement à `INSTALLATION.md`
(qui dit *quoi faire*), ce document explique *comment on trouve la cause*
d'une erreur Symfony — la méthode compte plus que le bug lui-même.

## Méthode générale

Face à une erreur 500 Symfony, dans cet ordre :

1. **Lire le message d'exception en entier**, pas juste le titre. Symfony
   donne quasi toujours la cause exacte et parfois la commande à lancer
   (ex. « run `php bin/console tailwind:build` »).
2. **Remonter la stack trace** jusqu'au premier fichier qui appartient au
   projet (`src/`, `config/`, `templates/`) plutôt que de s'arrêter sur la
   ligne de `vendor/` où l'exception est *levée* — elle n'y est pas forcément
   *causée*.
3. **Vérifier la config avant de soupçonner le code applicatif.** Les 4 bugs
   ci-dessous sont tous des oublis de configuration ou de champ, jamais un
   bug de logique métier.
4. **`git log --oneline -- <fichier>`** pour savoir depuis quand un fichier
   n'a pas changé, et **`git show <commit> -- <fichier>`** pour voir si une
   modification récente a supprimé quelque chose sans le vouloir.
5. **Après un `cache:clear`, si le comportement ne change toujours pas**,
   voir le piège du serveur `php -S` en bas de ce document avant de remettre
   en cause le correctif — c'est très probablement le serveur, pas la config.

## Cas 1 — CSS absent (Tailwind jamais compilé)

**Symptôme :** page blanche / erreur 500 sur `/contact` :
> Built Tailwind CSS file does not exist: run "php bin/console tailwind:build" to generate it

**Investigation :** le message d'erreur donne directement la commande à
lancer. Vérification : `assets/styles/app.css` (la source) existait bien,
mais le bundle `symfonycasts/tailwind-bundle` n'avait jamais généré son
fichier compilé.

**Cause racine :** `php bin/console tailwind:build` n'avait jamais été
exécuté après `composer install` — ce n'est pas un package npm, donc pas de
`npm install` pour le rappeler ; c'est une étape Symfony explicite et facile
à oublier.

**Correctif :** lancer la commande (télécharge le binaire Tailwind au
passage). Documenté comme étape obligatoire dans `INSTALLATION.md`, avec la
recommandation d'utiliser `--watch` en dev pour ne pas avoir à y repenser.

**À retenir :** quand Symfony dit littéralement quelle commande lancer,
c'est presque toujours la bonne première piste — ne pas chercher plus loin
avant de l'avoir essayée.

## Cas 2 — Composants Twig d'EasyAdmin introuvables

**Symptôme :** après connexion, 500 sur `/admin` :
> Unknown component "ea:ActionMenu:ActionList:Divider". And no matching anonymous component template was found.

**Investigation :** le fichier `Divider.html.twig` existait bel et bien
dans `vendor/easycorp/easyadmin-bundle/templates/components/ActionMenu/ActionList/`,
et `php bin/console debug:twig` confirmait que le namespace Twig `@ea`
pointait au bon endroit. Le problème n'était donc pas un fichier manquant,
mais une histoire de *résolution* du nom de composant.

En lisant le code source de `symfony/ux-twig-component`
(`ComponentTemplateFinder.php`), deux modes de résolution existent :
- un mode « legacy » (déprécié) qui ignore les préfixes de namespace comme
  `ea:` ;
- un mode « moderne », activé seulement si `anonymous_template_directory`
  est explicitement configuré, qui sait suivre `ea:Xxx` jusqu'au bon
  namespace Twig.

**Cause racine :** `config/packages/twig_component.yaml` n'existait tout
simplement pas dans le projet, donc le bundle restait en mode legacy — un
mode incompatible avec la façon dont EasyAdminBundle 5.x construit son
interface.

**Correctif :**
```yaml
# config/packages/twig_component.yaml
twig_component:
    anonymous_template_directory: 'components/'
```

**À retenir :** un « fichier introuvable » n'est pas toujours un problème de
fichier manquant — ça peut être un problème de *comment on cherche*. Quand
un fichier existe visiblement au bon endroit mais que l'erreur persiste,
c'est le signe qu'il faut lire le code de résolution plutôt que de
recompter les chemins à l'œil.

## Cas 3 — Statut du lead invisible dans le back-office

**Symptôme :** le champ « statut » existe en base (colonne `status`,
migration appliquée), mais n'apparaît nulle part dans la liste ou le
formulaire d'édition des leads sur `/admin`.

**Investigation :** `git log --oneline -- src/Controller/Admin/LeadCrudController.php`
puis `git show <commit>` sur chaque commit touchant ce fichier. Le commit
`feat(lead): add lead status` avait bien ajouté le champ à l'entité
`Lead` et à la migration, mais n'avait touché **que** ces deux fichiers —
jamais `LeadCrudController::configureFields()`.

**Cause racine :** dans EasyAdmin, un champ d'entité n'est visible dans le
back-office que s'il est explicitement listé dans `configureFields()`. Il
ne suffit pas qu'il existe sur l'entité : ajouter une colonne en base et
l'exposer dans l'admin sont deux étapes distinctes, et la seconde avait été
oubliée.

**Correctif :** ajout d'un `ChoiceField::new('status', 'Statut')` avec les
4 valeurs possibles (`Lead::STATUSES`) dans `configureFields()`.

**À retenir :** quand une fonctionnalité « existe en base mais n'apparaît
pas à l'écran », chercher la couche qui manque plutôt que la donnée
elle-même — ici, l'entité et la persistance étaient parfaites, c'est la
couche de présentation (EasyAdmin) qui n'était jamais reliée.

## Cas 4 — Déconnexion renvoie vers la page Symfony par défaut

**Symptôme :** après clic sur « Se déconnecter », l'utilisateur atterrit
sur la page de bienvenue Symfony au lieu de `/login`.

**Investigation :** `config/packages/security.yaml`, bloc `logout:`,
contenait un commentaire explicite laissé en place :
```yaml
logout:
    path: app_logout
    # where to redirect after logout
    # target: app_any_route
```

**Cause racine :** sans `target` défini, Symfony redirige par défaut vers
`/` après déconnexion. Or ce projet n'a **aucune route** sur `/` (voir la
toute première question de cette série de conversations !) — Symfony
affiche donc sa page de bienvenue par défaut, faute de mieux.

**Correctif :** `target: app_login`.

**À retenir :** ce bug est le même symptôme que la toute première question
posée sur ce projet (« pourquoi `/` affiche la page Symfony par défaut ? »)
mais avec une cause différente (redirection de logout vs absence de route
d'accueil). Un même symptôme peut avoir plusieurs causes distinctes selon
le chemin qui y mène — toujours vérifier l'URL réellement atteinte et
comment on y est arrivé, pas juste ce qui s'affiche.

## Piège transverse — le serveur `php -S` garde son cache en mémoire

Rencontré en corrigeant le Cas 2 : après avoir ajouté
`twig_component.yaml` et lancé `php bin/console cache:clear`, l'erreur
persistait à l'identique en rechargeant la page.

**Explication :** `php -S 127.0.0.1:8080 -t public` démarre **un seul
processus PHP qui reste actif** pour toute la session de dev (contrairement
à un vrai serveur web qui recharge le code à chaque requête via PHP-FPM).
`cache:clear` régénère bien `var/cache/dev/` sur le disque, mais le
processus déjà lancé a chargé la classe du conteneur Symfony compilé **en
mémoire** au premier démarrage — PHP ne peut pas redéfinir une classe déjà
chargée, donc le processus continue de servir l'ancien conteneur tant qu'il
tourne.

**Solution :** après tout changement dans `config/`, arrêter le process
`php -S` (`taskkill`/`Ctrl+C`) et le relancer, en plus (pas à la place) du
`cache:clear`.

**À retenir :** si un correctif de configuration semble ne « rien
changer » après un `cache:clear`, soupçonner le serveur avant de
soupçonner le correctif.
