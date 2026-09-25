# Changelog

Toutes les évolutions notables de Rocket Cloud. Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le projet respecte le [versionnage sémantique](https://semver.org/lang/fr/).

## [Non publié]

## [0.1.0] - 2026-09-25

Première version de Rocket Cloud, la brique de stockage et de partage de fichiers du Middleware Rocket.

### Ajouté

- **Mes fichiers** : un espace privé par utilisateur, avec dossiers et sous-dossiers, fil d'Ariane, dépôt de plusieurs fichiers par bouton ou par glisser-déposer, renommage, déplacement, suppression (avec le contenu du dossier), recherche dans tous les dossiers, et jauge de l'espace utilisé.
- **Aperçu** dans le navigateur des images, PDF, textes, sons et vidéos ; téléchargement des autres fichiers. Le contenu est servi avec une politique de sécurité stricte (`sandbox`, `nosniff`).
- **Stockage** : contenu rangé sous `DATA_DIR/files` sous un nom opaque, type détecté d'après le contenu, empreinte SHA-256. Quota par utilisateur `CLOUD_DEFAULT_QUOTA` (10 Go) et taille maximale `CLOUD_MAX_FILE_SIZE` (512 Mo).
- **Liens de partage** vers un fichier ou un dossier (sous-dossiers compris), ouverts sans compte sur `/s/<jeton>` : mot de passe (haché, envoyé en `POST`, jamais dans l'adresse), date d'expiration, nombre maximal de téléchargements, message. Page **Mes partages** : état (actif, expiré, épuisé), téléchargements, copie du lien, suppression.
- **Notifications par email** : le lien est envoyé aux destinataires (20 au maximum) par **Rocket Mailer**, en arrière-plan et au nom de l'utilisateur (`ROCKET_MAILER_URL`, `ROCKET_MAILER_TOKEN`, `FRONTEND_URL`). L'état de l'envoi s'affiche dans **Mes partages**.
- **API** : `POST /api/files` (multipart), `GET /api/files`, `GET`, `PATCH` et `DELETE /api/files/{id}`, `GET /api/files/{id}/content`, `GET /api/files/usage`, `/api/folders` (CRUD), `/api/shares` (création, liste, suppression), et côté public `GET /api/public/shares/{token}` et `POST /api/public/shares/{token}/download`. Les applications travaillent dans l'espace d'un utilisateur (`X-Impersonate-User`).
- **Tableau de bord** : mon espace et le quota, fichiers ajoutés sur 30 jours, fichiers et volume, partages et téléchargements, derniers fichiers, nouveaux partages dans l'activité. Les administrateurs voient les chiffres de la plateforme, jamais les fichiers des autres. Rocket Mailer apparaît dans l'état des services, vérifié toutes les 5 minutes.
- **Démo** : un dossier « Projets » pour Alice avec deux fichiers et un lien de partage ; la CI lance la démo complète.
- **Socle commun Rocket**, partagé avec Rocket Auth, Rocket Mailer et Rocket Print : configuration initiale, comptes locaux, LDAP et SSO (OpenID Connect, Rocket Auth), modes autonome et suite, serveurs d'authentification, applications externes (jeton `rca_…`) et impersonation, tableau de bord extensible, sondes de santé, version et mises à jour (Docker, serveur sans Docker, manuelle), environnement de démo et Codespaces.

### Sécurité

- Chaque espace est privé : les listes de dossiers, de fichiers et de liens ne contiennent que ceux de l'utilisateur, administrateurs compris.
- Un lien de partage cesse de fonctionner dès que son propriétaire est désactivé.
