# Environnement de démo

## Dans GitHub Codespaces (rien à installer)

1. Sur GitHub, ouvre le dépôt, choisis la branche qui contient la démo, puis **Code → Codespaces → Create codespace on …**.
2. Attends la fin de la commande de démarrage dans le terminal (5 à 10 minutes au premier lancement, le temps de construire les images). Elle affiche les URLs de la démo.
3. Dans l'onglet **Ports**, ouvre « Rocket Cloud » (3200) ou « Documentation et changelog » (3201). Depuis Rocket Cloud, `/docs` et `/changelog` y mènent aussi.

> ⚠️ Un port public est accessible à toute personne qui a l'URL, et les mots de passe de démo sont publics. Arrête le codespace quand tu as fini (menu Codespaces → *Stop codespace*). Le quota gratuit de GitHub est limité en heures par mois.

Pour relancer la démo à la main : `bash demo/codespaces/start.sh`.

## En local

Pré-requis : Docker avec Compose v2.24 ou plus récent.

```bash
docker compose -f compose.yaml -f compose.demo.yaml up -d --build
```

Le premier démarrage prend quelques minutes (build des images). Le service `demo-seed` prépare la base, charge les données de démo et synchronise l'annuaire LDAP, puis s'arrête. Pour suivre sa progression : `docker compose -f compose.yaml -f compose.demo.yaml logs -f demo-seed`.

| Adresse | Contenu |
|---|---|
| http://localhost:3200 | Rocket Cloud |
| http://localhost:3201 | Documentation, et le changelog sur `/changelog` |
| http://localhost:8200/api/docs | Documentation de l'API |

## Comptes

| Compte | Mot de passe | Type |
|---|---|---|
| `admin@example.org` | `demo-admin-password` | local, administrateur |
| `alice@example.org` | `demo-alice-password` | local |
| `marie.martin@example.org` | `password` | LDAP, admin via le groupe `rocket-admins` |
| `jean.dupont@example.org` | `password` | LDAP |

Alice a un dossier **Projets** avec « Note de lancement.txt » et « Tarifs 2026.csv », et un lien de partage vers les tarifs. L'« Application de démo » peut agir au nom des utilisateurs, avec le jeton de `compose.demo.yaml`.

## Scénarios à tester

1. **Ranger ses fichiers.** Connecte-toi avec `alice@example.org`, ouvre *Mes fichiers* puis *Projets*. Crée un sous-dossier, glisse-y des fichiers, ouvre un PDF ou une image dans le navigateur, déplace et renomme un fichier. La jauge montre l'espace utilisé.
2. **Ouvrir un lien de partage.** Dans *Mes partages*, copie le lien vers « Tarifs 2026.csv » et ouvre-le dans une fenêtre de navigation privée : la page publique affiche le message d'Alice et permet de télécharger, sans compte.
3. **Partager un dossier protégé.** Partage *Projets* avec un mot de passe et 3 téléchargements au maximum : la page publique demande le mot de passe avant de lister les fichiers. Supprime le lien dans *Mes partages* : il ne s'ouvre plus.
4. **Espaces privés.** Connecte-toi avec `admin@example.org` : *Mes fichiers* est vide, les fichiers d'Alice ne sont pas visibles ; le tableau de bord montre les chiffres de toute la plateforme.
5. **Connexion LDAP.** Connecte-toi avec `marie.martin@example.org` / `password` : elle est administratrice grâce à son groupe LDAP. Dans *Utilisateurs*, *Synchroniser LDAP* relance la synchronisation.
6. **Application et impersonation.** Une application dépose un fichier dans l'espace d'Alice :
   ```bash
   curl -X POST http://localhost:3200/api/files \
     -H "Authorization: Bearer rca_demo_rocket_auth_do_not_use_in_production" \
     -H "X-Impersonate-User: alice@example.org" -H "Accept: application/json" \
     -F file=@document.pdf
   ```
   En impersonnant `admin@example.org`, l'application n'obtient pas pour autant les droits administrateur (`GET /api/me`).

La démo ne comprend pas Rocket Mailer : un partage avec des destinataires est créé, mais *Mes partages* indique que l'email n'est pas parti.

## Réinitialiser

```bash
docker compose -f compose.yaml -f compose.demo.yaml down -v
```

> Cette démo utilise des mots de passe et un jeton d'application publics (`compose.demo.yaml`). Ne l'expose jamais sur Internet.
