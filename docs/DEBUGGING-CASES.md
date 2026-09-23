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

## Cas 5 — L'historisation ne se déclenchait que depuis EasyAdmin

**Contexte :** la PR #32 ajoutait un `StatusHistorySubscriber` qui écoutait
`BeforeEntityUpdatedEvent` d'EasyAdminBundle pour enregistrer chaque
changement de statut dans `status_history`. En review, avant de merger, on
s'est demandé : que se passe-t-il si le statut change **sans passer par le
formulaire d'édition EasyAdmin** ?

**Investigation :** `BeforeEntityUpdatedEvent` est un événement du *bundle*
EasyAdmin, pas de Doctrine. Il n'est dispatché que par le contrôleur CRUD
d'EasyAdmin, quand une sauvegarde passe par son formulaire web. Un
changement de statut fait autrement — une commande console, un script de
fixtures, une future route API publique, ou même un `$lead->setStatus(...)`
suivi d'un `flush()` ailleurs dans le code — ne passe jamais par ce
contrôleur, donc l'événement n'est jamais dispatché, et l'historique reste
silencieusement incomplet. Pas d'erreur, pas de log : juste des trous dans
l'audit trail, découverts uniquement le jour où on en a besoin.

**Cause racine :** avoir accroché une logique métier (« toujours tracer les
changements de statut ») à un événement de *framework d'admin* plutôt qu'à
un événement de la *couche de persistance*. EasyAdmin n'est qu'un des
chemins possibles pour modifier un `Lead` ; Doctrine, lui, voit *tous* les
chemins, puisque tout finit par un `flush()`.

**Correctif :** remplacer l'écoute de `BeforeEntityUpdatedEvent` (EasyAdmin)
par l'écoute de `Events::onFlush` (Doctrine ORM), via l'attribut
`#[AsDoctrineListener(event: Events::onFlush)]`. Voir aussi le Cas 6
ci-dessous : le choix de l'événement Doctrine précis (`preUpdate` vs
`onFlush`) a son propre piège.

**À retenir :** quand une règle doit s'appliquer « à chaque fois que X
change en base », se demander à quelle couche l'accrocher. Un événement de
bundle (EasyAdmin, une future API REST, etc.) ne couvre que *son propre*
chemin d'écriture. Un événement Doctrine (`onFlush`, `preUpdate`,
`postPersist`...) couvre *tous* les chemins, parce qu'ils passent tous par
l'`EntityManager`.

## Cas 6 — `preUpdate` ne suffit pas pour créer une entité liée dans le même flush

**Contexte :** première tentative de correctif du Cas 5, écrire un listener
Doctrine sur `Events::preUpdate` :

```php
public function preUpdate(PreUpdateEventArgs $event): void
{
    // ... détecter le changement de statut ...
    $statusHistory = new StatusHistory();
    // ...
    $em->persist($statusHistory);
    $em->getUnitOfWork()->computeChangeSet(
        $em->getClassMetadata(StatusHistory::class),
        $statusHistory
    );
}
```

C'est la recette qu'on trouve dans beaucoup de tutoriels Doctrine plus
anciens pour « créer une entité depuis un listener ».

**Symptôme :** aucune erreur, aucune exception — mais un script de test
direct (créer un `Lead`, changer son statut, `flush()`, relire la base)
montrait `status_history` toujours vide. Le listener était bien appelé
(vérifié avec un `error_log` temporaire dans la méthode), et
`computeChangeSet()` ne levait rien.

**Investigation :** `preUpdate` se déclenche **pendant** l'exécution des
requêtes SQL du `flush()` (dans `executeUpdates()`), après que Doctrine a
déjà figé la liste des entités à insérer (`entityInsertions`). Appeler
`persist()` + `computeChangeSet()` à ce moment-là calcule bien le changeset
de la nouvelle entité, mais ne l'ajoute pas à la liste des insertions déjà
en cours d'exécution pour ce `flush()` — elle est donc calculée puis
oubliée.

**Cause racine :** mauvais événement pour ce besoin. `onFlush` (pas
`preUpdate`) se déclenche **avant** que Doctrine construise ses listes
d'insertions/mises à jour/suppressions à partir des changesets. C'est le
seul moment du cycle de vie où persister une nouvelle entité liée est
garanti d'être pris en compte dans le même `flush()`.

**Correctif :** réécrire le listener sur `onFlush`, en itérant
`$unitOfWork->getScheduledEntityUpdates()` pour retrouver les `Lead`
modifiés (au lieu de recevoir une entité à la fois comme le fait
`preUpdate`) :

```php
#[AsDoctrineListener(event: Events::onFlush)]
class StatusHistorySubscriber
{
    public function onFlush(OnFlushEventArgs $event): void
    {
        $em = $event->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Lead) {
                continue;
            }
            // ... créer StatusHistory, $em->persist(), computeChangeSet() ...
        }
    }
}
```

Revalidé avec le même script de test : `status_history` contient bien une
ligne après chaque changement de statut, y compris via un simple
`$lead->setStatus(...); $em->flush();` en dehors de tout contrôleur admin.

**À retenir :** « ça ne lève pas d'exception » ne veut pas dire « ça
marche » — ici le code s'exécutait sans erreur mais n'écrivait rien. Pour
une fonctionnalité invisible en base malgré un code qui semble correct,
écrire un petit script de bout en bout (créer → modifier → relire) est plus
fiable que de relire le code en se fiant à l'absence d'erreur. Et pour
« créer une entité liée depuis un listener Doctrine », `onFlush` est le bon
réflexe, pas `preUpdate` — même si d'anciens tutoriels disent le contraire.

## Cas 7 — Un logo en `<img>` dans `setTitle()` rendait le tooltip vide

**Symptôme :** après avoir remplacé le texte « MOSLTRANS » du logo
EasyAdmin par une image (`Dashboard::new()->setTitle('<img src="..."
alt="MOSLTRANS">')`), le logo s'affichait correctement, mais le tooltip
(l'infobulle au survol du logo) avait disparu sur toutes les pages admin.

**Investigation :** dans `vendor/easycorp/easyadmin-bundle/templates/
layout.html.twig`, le titre du dashboard est utilisé deux fois à deux
endroits différents : `{{ ea.dashboardTitle|raw }}` pour l'affichage (HTML
brut, donc l'`<img>` s'affiche bien), et `title="{{
ea.dashboardTitle|striptags }}"` pour le tooltip du lien — `striptags`
retire les balises et ne garde que le texte. Un `<img alt="MOSLTRANS">` n'a
pas de texte *entre* ses balises (l'attribut `alt` n'en est pas un pour
`striptags`), donc le résultat est une chaîne vide.

**Cause racine :** confusion entre l'attribut `alt` d'une image (utilisé
par les lecteurs d'écran quand l'image ne se charge pas) et le texte
« visible » qu'un filtre comme `striptags` peut extraire. Ce sont deux
mécanismes différents.

**Correctif :** ajouter du texte réel à côté de l'image, caché visuellement
mais lisible par `striptags` (et par les lecteurs d'écran) via la classe
utilitaire Tailwind `sr-only` :

```php
sprintf(
    '<img src="%s" alt="" class="brand-logo"><span class="sr-only">MOSLTRANS</span>',
    $logoUrl
)
```

(`alt=""` sur l'image car le texte du `<span>` porte déjà l'information —
éviter que les deux soient annoncés en double par un lecteur d'écran.)

**À retenir :** quand un composant tiers (ici EasyAdmin) réutilise une même
valeur à deux endroits avec des filtres différents (`|raw` vs
`|striptags`), tester le rendu HTML final aux *deux* endroits, pas
seulement celui qu'on modifie en premier. Une régression peut être
invisible à l'écran (le logo s'affichait très bien) tout en cassant
l'accessibilité ou l'UX ailleurs.

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
