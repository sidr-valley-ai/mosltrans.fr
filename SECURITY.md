# Sécurité — gestion des secrets

Ce projet est un **repo public**. Tout ce qui est committé y est visible indéfiniment,
même après suppression dans un commit suivant : un secret retiré du fichier reste
lisible dans l'historique tant que personne ne va explicitement le chercher.

## Règle : aucun secret dans un fichier versionné

- `.env`, `.env.dev` : valeurs par défaut **non sensibles** uniquement (le repo les
  committe volontairement, donc `APP_SECRET=` doit y rester vide).
- `.env.local` : c'est le seul endroit pour une vraie valeur. Il n'est **jamais**
  versionné (`.gitignore` l'exclut), donc chaque personne a son propre fichier,
  jamais partagé ni committé.

Avant tout `git add`/`git commit`, un `git status` permet de repérer un `.env.local`
qui apparaîtrait par erreur dans les fichiers suivis.

## Générer sa propre valeur locale

```bash
# Linux/macOS/Git Bash
openssl rand -hex 16

# Alternative sans openssl
php -r "echo bin2hex(random_bytes(16)), \"\n\";"
```

Copier le résultat dans `.env.local` :

```
APP_SECRET=<valeur générée ci-dessus>
```

Chaque développeur génère sa propre valeur — elles n'ont pas besoin d'être identiques
entre les machines (dev uniquement, pas de session partagée entre environnements).

## Si un secret a quand même été committé

C'est arrivé une fois sur ce projet (Sprint 1, `APP_SECRET` committé dans `.env.dev`
puis retiré, mais toujours lisible dans l'historique des anciennes Pull Requests).
Le corriger dans le fichier ne suffit pas — la marche à suivre :

1. Considérer la valeur comme définitivement compromise, même après correction.
2. Chaque personne régénère sa propre valeur locale (commande ci-dessus) et met à
   jour son `.env.local` — jamais la même valeur que celle qui a fuité.
3. Ne pas rouvrir un ticket public décrivant *quelle* valeur a fuité : ça n'apporte
   rien (rien à corriger côté code, `.env.local` n'est pas versionné) et ça
   republierait l'information inutilement sur un repo public.
4. Si une branche/PR de sauvegarde contenant l'ancien historique n'est plus utile,
   la supprimer réduit la surface d'exposition (sans garantir un nettoyage complet :
   un fork externe ou un clone déjà fait aurait pu la récupérer avant).
