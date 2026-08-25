# mosltrans.fr — site vitrine B2B

Site vitrine pour MOSLTRANS, réalisé par une stagiaire pour Sidr Valley AI (projet
école, backend Symfony imposé par le cursus). Objectif double : visibilité pour
MOSLTRANS et cas d'usage portfolio réel pour Sidr Valley AI.

**Périmètre** : site marketing standalone, indépendant de l'app Fretexia (pas de
connexion technique entre les deux — aucun risque sur le système de production
Fretexia/MOSLTRANS).

## Stack technique

| Couche | Techno | Rôle |
|--------|--------|------|
| Backend | Symfony (PHP) | back-office leads, formulaires, auth admin, ORM Doctrine |
| Base de données | SQLite | relationnel, fichier unique, pas de serveur DB à déployer |
| Front — rendu | Twig | templates HTML rendus côté serveur |
| Front — interactivité | HTMX | requêtes/swaps HTML sans recharger la page |
| Front — comportement JS | Alpine.js | petites interactions client (dropdown, toggle...) |
| Front — style | Tailwind CSS (via AssetMapper) | design sur-mesure, sans étape de build npm |
| Front — configurateur devis | React (option, plus tard) | composant isolé, pas de SPA globale |

## Backend Symfony — périmètre retenu

Un formulaire de contact "email only" ne justifie pas un framework backend complet.
Le périmètre retenu est le minimum qui donne un vrai contenu pédagogique Symfony sans
inventer un besoin métier factice :

### 1. Back-office de gestion des leads (coeur du projet)

- Formulaire de contact → écriture en base (pas juste un envoi d'email)
- Écran admin authentifié listant les demandes reçues
- Statut par lead : `nouveau` / `contacté` / `converti`

Couvre : routing, Doctrine ORM, formulaires + validation, Security component (auth),
CRUD — éventuellement via EasyAdminBundle pour aller plus vite sur la partie back-office.

### 2. Historisation des statuts + modèles d'e-mails (préconisation ENI, retenue)

Pour mieux coller aux attendus du Titre Professionnel Développeur Web et Web
Mobile — traité juste après le coeur du projet (1), avant le configurateur de
devis :

- **Historisation des statuts** : au lieu d'un simple champ statut sur le
  lead, tracer chaque changement (date, statut précédent/nouveau) pour
  gérer un vrai workflow de prise de contact / relance à une date précise.
- **Modèles d'e-mails** : gérer des templates d'e-mail avec du contenu
  dynamique (variables injectées : nom du lead, statut, date de relance...)
  plutôt que des e-mails en dur dans le code.

### 3. Configurateur de devis indicatif (option, si le temps le permet)

- Saisie : type de transport + zone géographique
- Calcul d'une estimation indicative (pas un devis contractuel)
- Le résultat alimente le même back-office leads que le formulaire de contact
  (même statut nouveau/contacté/converti)

À ne faire qu'une fois le back-office leads stable — c'est un bonus, pas une
dépendance du coeur du projet.

## Front — périmètre retenu (2026-08-20)

- **Site vitrine** : Twig (rendu serveur Symfony natif) + HTMX/Alpine.js pour
  l'interactivité légère (préféré à Symfony UX/Stimulus-Turbo : même philosophie
  hypermedia, mais déjà connu côté Fretexia et sans étape de build). Mono-stack
  avec le backend, meilleur SEO natif qu'une SPA — important pour un site pensé
  pour générer des leads.
- **React** : réservé au configurateur de devis (l'option ci-dessus), et
  seulement quand ce composant sera attaqué — pas de SPA globale. À monter
  comme un composant React isolé dans une page Twig, pas comme un front séparé.

### Tailwind CSS vs Bootstrap

Tailwind est retenu plutôt que Bootstrap, pour trois raisons :

- **Différenciation** : le site est aussi un cas d'usage portfolio pour Sidr
  Valley AI. Bootstrap "par défaut" a un rendu générique reconnaissable ;
  Tailwind n'impose aucun composant visuel, donc rien à désapprendre pour
  obtenir une identité visuelle propre à MOSLTRANS plutôt qu'un thème
  Bootstrap habillé.
- **Cohérence avec le choix "pas de build npm/webpack"** déjà fait pour
  HTMX/Alpine (cf. section Front ci-dessus) : via
  `symfonycasts/tailwind-bundle` (AssetMapper natif Symfony), Tailwind
  compile via un binaire CLI autonome, sans Node ni pipeline JS à
  maintenir — même philosophie mono-stack Symfony que le reste du projet.
- **Utility-first plutôt que composants pré-stylés** : mieux adapté à des
  gabarits Twig sur-mesure (hero, sections marketing, formulaire de
  contact, back-office leads) qu'aux composants JS de Bootstrap
  (modals, dropdowns...), qui feraient doublon avec Alpine.js.

Contrepartie assumée : plus de classes utilitaires à écrire à la main qu'avec
les composants prêts-à-l'emploi de Bootstrap, donc une légère montée en
compétence CSS à prévoir pour la stagiaire — compensée par la documentation
officielle Tailwind, très complète.

## Hors périmètre (explicitement écarté)

- Un vrai CRM complet
- Toute forme de multi-tenant
- Toute intégration technique avec l'app Fretexia

## Documentation

- [Cycle de vie du développement logiciel (SDLC)](https://claude.ai/code/artifact/3a4456c4-9867-4636-be88-1163ea4f0c43) — guide de formation pour la stagiaire : phases du SDLC, modèles (cascade/Scrum/Kanban/DevOps), bonnes pratiques et ressources.
- [Stratégie d'Authentification](https://claude.ai/code/artifact/bbcda6c1-35b8-43d3-b4bc-66114b96a42c) — guide de formation pour la stagiaire : choix et mise en oeuvre de l'auth admin côté Symfony.
- [Vulnérabilités Web](https://claude.ai/code/artifact/0cef7311-19d8-4a3d-9e93-aa8f52ba545f) — guide de formation pour la stagiaire : catalogue des failles courantes (XSS, injection SQL, CSRF...) issues d'OWASP/CERT-FR/CVE, appliquées à mosltrans.fr et à l'authentification par session.
- [RGPD by Design](https://claude.ai/code/artifact/4a941a50-f405-46cd-8031-3efbd466a1ad) — guide de formation pour la stagiaire : base légale, minimisation, conservation, droits des personnes et Privacy by Design appliqués à la collecte de leads B2B sur mosltrans.fr.
- [Stratégie Git](https://claude.ai/code/artifact/f209da17-90e3-4b76-b27d-d8f7bfb4925e) — guide de formation pour la stagiaire : modèle de branches, commits atomiques et Conventional Commits en français, cycle complet d'une tâche (branche → commits → PR → review → merge). À lire avant l'initialisation du dépôt.
