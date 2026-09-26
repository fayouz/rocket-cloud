# Changelog

Toutes les évolutions notables de Rocket Cloud. Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le projet respecte le [versionnage sémantique](https://semver.org/lang/fr/).

## [Non publié]

### Ajouté

- **Sélecteur de fichiers intégrable** : `embed.js` (`RocketCloud.mount()` et le web component `<rocket-cloud-picker>`) affiche dans une autre application les dossiers et fichiers de son utilisateur, pour choisir un fichier (filtré par type : `accept`, par exemple `.docx`) ou un dossier (`mode="folder"`). Page `/embed/picker`, jeton d’intégration délivré par l’application, origines autorisées dans **Administration → Applications** ; une session d’intégration ne fait que lire. Utilisé par Rocket Dispatch pour sa bibliothèque de modèles.
- **Remplacer le contenu d’un fichier** : `PUT /api/files/{id}/content` (corps brut) ou `POST` (multipart `file`) garde le fichier (identifiant, nom, dossier, liens de partage) avec son nouveau contenu, dans le quota et la taille maximale. Les applications enregistrent ainsi la nouvelle version d’un document retouché ailleurs.
- **Suite Rocket, appels à Rocket Mailer sans jeton statique** : en mode suite, les notifications de partage partent avec un jeton d'accès de Rocket Auth (identifiants client, audience `rocket-mailer`, `ROCKET_MAILER_AUDIENCE`), que Rocket Mailer rattache à l'application liée au client `rocket-cloud`. `ROCKET_MAILER_TOKEN` reste utilisé en mode autonome, et en secours si Rocket Mailer refuse le jeton de la suite.
- **Déconnexion back-channel** : se déconnecter de Rocket Auth, ou y être désactivé ou supprimé, ferme les sessions de l'utilisateur dans Rocket Cloud (`POST /api/auth/oidc/backchannel-logout`, adresse déclarée à Rocket Auth ; `ROCKET_INTERNAL_URL` quand Rocket Auth joint Rocket Cloud par une adresse interne).

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
