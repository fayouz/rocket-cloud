# Rocket Cloud

Stockage et partage de fichiers, depuis le navigateur ou depuis vos applications : un **espace privé** par utilisateur (dossiers, dépôt par glisser-déposer, aperçu, quota), des **liens de partage** publics (mot de passe, expiration, limite de téléchargements) envoyés par email via Rocket Mailer, et une API pour déposer et relire des fichiers au nom des utilisateurs. Brique du Middleware Rocket, sur la même stack que [Rocket Auth](https://github.com/fayouz/rocket-auth), [Rocket Mailer](https://github.com/fayouz/rocket-mailer) et [Rocket Print](https://github.com/fayouz/rocket-print).

| Dossier | Stack |
|---|---|
| `backend/` | Symfony 8.1, API Platform 5, Doctrine ORM 3 (PostgreSQL), StofDoctrineExtensions, LexikJWT, Messenger et Scheduler, LDAP |
| `frontend/` | Nuxt 4, Nuxt UI 4 |
| `docs/` | Site de documentation (Nuxt UI + Nuxt Content), avec le changelog sur `/changelog` : `cd docs && npm install && npm run dev`, puis http://localhost:3201 |

Le socle commun (comptes, LDAP, SSO, applications, tableau de bord, mises à jour, modes autonome et suite) vient de **[rocket-core](https://github.com/fayouz/rocket-core)** : le bundle Symfony `rocket/core-bundle` (Composer) et le layer Nuxt `@rocket/core` (npm). Pour travailler sur les deux à la fois : `ROCKET_CORE_LAYER=../../rocket-core/nuxt npm run dev` côté front, et un dépôt `path` Composer côté backend.

## Démarrage rapide

```bash
docker compose up -d --build
```

Au premier lancement, http://localhost:3200 affiche la **configuration initiale** : on y crée le compte administrateur. Si l'instance est exposée avant d'être configurée, définissez `SETUP_TOKEN`. L'administrateur peut aussi être créé en ligne de commande : `docker compose exec api php bin/console app:user:create admin@example.org 'un-mot-de-passe-long' --admin`.

Pour envoyer les liens de partage par email, renseignez `ROCKET_MAILER_URL` et `ROCKET_MAILER_TOKEN` (voir plus bas).

- Application : http://localhost:3200
- API + documentation OpenAPI : http://localhost:8200/api/docs

### Démo prête à tester

`docker compose -f compose.yaml -f compose.demo.yaml up -d --build` lance une démo complète : comptes locaux et LDAP, des fichiers et un lien de partage pour Alice, une application externe au jeton connu, et la documentation sur http://localhost:3201. Voir [demo/README.md](demo/README.md).

### Développement sans Docker

```bash
# backend (PHP 8.4, PostgreSQL)
cd backend && composer install
php bin/console lexik:jwt:generate-keypair
php bin/console doctrine:migrations:migrate
echo 'MESSENGER_TRANSPORT_DSN=sync://' >> .env.local   # ou lancer messenger:consume async scheduler_default
php -S 127.0.0.1:8200 -t public
php bin/phpunit

# frontend
cd frontend && npm install && npm run dev -- --port 3200   # NUXT_PUBLIC_API_BASE=http://localhost:8200
```

## Fonctionnalités

### Mes fichiers
- Un **espace privé** par utilisateur : dossiers et sous-dossiers, dépôt de plusieurs fichiers par bouton ou glisser-déposer, renommage, déplacement, suppression (un dossier avec tout son contenu), recherche dans tous les dossiers.
- **Aperçu** dans le navigateur des images, PDF, textes, sons et vidéos ; les autres fichiers se téléchargent. Le contenu est servi avec `Content-Security-Policy: … sandbox` et `nosniff`.
- Stockage sous `DATA_DIR/files` (volume `app_data`, partagé par l'API et le worker), sous un nom opaque ; type détecté d'après le contenu, empreinte SHA-256.
- Quota par utilisateur `CLOUD_DEFAULT_QUOTA` (10 Go) et taille maximale `CLOUD_MAX_FILE_SIZE` (512 Mo, dans la limite de `backend/docker/php.ini`).
- Personne d'autre n'accède à l'espace d'un utilisateur, **administrateurs compris** : ils voient des chiffres sur le tableau de bord, pas les fichiers.

### Liens de partage
- Vers un fichier ou un dossier (sous-dossiers compris), ouverts **sans compte** sur `/s/<jeton>`.
- Options : mot de passe (haché, envoyé en `POST`, jamais dans une URL), date d'expiration, nombre maximal de téléchargements, message.
- **Mes partages** : état (actif, expiré, épuisé), nombre de téléchargements, copie du lien, suppression immédiate. Un lien cesse de fonctionner si son propriétaire est désactivé.
- **Envoi par email** aux destinataires (20 au maximum) par [Rocket Mailer](https://github.com/fayouz/rocket-mailer) : Rocket Cloud y est une application externe qui envoie au nom de l'utilisateur. `ROCKET_MAILER_URL` (API de Rocket Mailer), `ROCKET_MAILER_TOKEN` (jeton `rma_…` de l'application déclarée dans Rocket Mailer, avec impersonation ; inutile en mode suite, où Rocket Cloud utilise un jeton de Rocket Auth pour l'audience `rocket-mailer`) et `FRONTEND_URL` (base des liens). Vides : pas d'email, les liens se transmettent à la main.

### API pour les applications
```bash
curl -X POST https://cloud.exemple.com/api/files \
  -H "Authorization: Bearer rca_…" -H "X-Impersonate-User: alice@exemple.com" -H "Accept: application/json" \
  -F file=@devis.pdf -F folder=<id>
```
`GET /api/files` (filtres `folder`, `name`, `mimeType`, tri), `GET|PATCH|DELETE /api/files/{id}`, `GET /api/files/{id}/content[?download=1]`, `GET /api/files/usage`, `/api/folders` (CRUD), `/api/shares` (création, liste, suppression), et côté public `GET /api/public/shares/{token}` et `POST /api/public/shares/{token}/download`. Voir `docs/content/4.api/`.

### Socle commun Rocket (rocket-core)
- **Comptes** locaux, **LDAP** (synchronisation, rôle admin par groupe) et **SSO OpenID Connect** (Rocket Auth ou tout fournisseur).
- **Mode suite** : avec `ROCKET_AUTH_URL`, la connexion passe par Rocket Auth (`ROCKET_AUTH_INTERNAL_URL`, `ROCKET_AUTH_CLIENT_ID` — `rocket-cloud` par défaut —, `ROCKET_AUTH_CLIENT_SECRET`, `ROCKET_AUTH_ADMIN_GROUP` — `rocket-admins` par défaut —, `ROCKET_LOCAL_LOGIN`).
- **Applications externes** : jeton `rca_…` (seul son hash est stocké) et impersonation par `X-Impersonate-User`, jamais avec le rôle administrateur.
- **Tableau de bord** : espace utilisé, fichiers ajoutés, partages et téléchargements, état des services (base, tâches de fond, LDAP, SSO, Rocket Mailer, stockage).
- **Version et mises à jour** : Docker (Watchtower, profil `updater`), serveur sans Docker (`deploy/update.sh`) ou manuelle.
- **Traçabilité** : dossiers et fichiers sont Timestampable et Blameable.

## CI/CD

`.github/workflows/ci.yml` :
- à chaque push et pull request : lint du container, validation du schéma Doctrine, PHPUnit, puis ESLint, typecheck et build du front et de la documentation ; la démo complète est lancée et ses scénarios vérifiés ;
- sur `main`, `develop` et les tags `v*` : images `ghcr.io/fayouz/rocket-cloud-api` et `ghcr.io/fayouz/rocket-cloud-front`.

Le worker utilise l'image API avec `php bin/console messenger:consume async scheduler_default` (notifications de partage, et vérifications de santé planifiées).

## Gitflow

- `main` : production (images `latest` et tags `vX.Y.Z`) ; `develop` : intégration.
- `feature/*` : pull request vers `develop`, qui complète la section `[Non publié]` de [CHANGELOG.md](CHANGELOG.md), publiée sur `/changelog` dans la documentation.
- `release/*` et `hotfix/*` vers `main` : `[Non publié]` devient `[X.Y.Z] - date`, puis tag `vX.Y.Z`.
