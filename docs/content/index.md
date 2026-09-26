---
title: Rocket Cloud
description: Rangez vos fichiers, partagez-les par lien public et rendez-les disponibles pour vos applications.
seo:
  title: Rocket Cloud — Documentation
---

::u-page-hero
---
orientation: horizontal
title: Vos fichiers, rangés, partagés et disponibles pour vos applications.
---
#description
Rocket Cloud donne à chaque utilisateur un **espace privé** : dossiers, dépôt par glisser-déposer, aperçu, recherche. Un fichier ou un dossier se partage par un **lien public**, protégé par mot de passe, limité dans le temps ou en téléchargements, et envoyé par email via **Rocket Mailer**.

#links
  :::u-button
  ---
  to: /api/files
  size: xl
  trailing-icon: i-lucide-arrow-right
  ---
  Déposer depuis une application
  :::

  :::u-button
  ---
  to: /getting-started/introduction
  size: xl
  color: neutral
  variant: subtle
  icon: i-lucide-book-open
  ---
  Découvrir Rocket Cloud
  :::

#default
  ```bash [Terminal]
  curl -X POST https://cloud.exemple.com/api/files \
    -H "Authorization: Bearer rca_…" \
    -H "X-Impersonate-User: alice@exemple.com" \
    -F file=@devis-2026-042.pdf \
    -F folder=0199…
  # → 201 { "id": "…", "name": "devis-2026-042.pdf", … }
  ```
::

::u-page-section
#title
Ce que vous pouvez faire

#features
  :::u-page-feature
  ---
  icon: i-lucide-folder-tree
  to: /files/files
  ---
  #title
  Un espace par utilisateur

  #description
  Dossiers et sous-dossiers, dépôt par glisser-déposer, aperçu dans le navigateur, recherche, quota d'espace.
  :::

  :::u-page-feature
  ---
  icon: i-lucide-share-2
  to: /files/sharing
  ---
  #title
  Liens de partage

  #description
  Un lien public vers un fichier ou un dossier, sans compte : mot de passe, date d'expiration, nombre de téléchargements.
  :::

  :::u-page-feature
  ---
  icon: i-lucide-send
  to: /administration/storage#notifications-par-email
  ---
  #title
  Notifications par email

  #description
  Le lien part par email aux destinataires, au nom de l'utilisateur, par Rocket Mailer.
  :::

  :::u-page-feature
  ---
  icon: i-lucide-code
  to: /api/files
  ---
  #title
  API pour vos applications

  #description
  Vos applications déposent et lisent des fichiers au nom de leurs utilisateurs (impersonation), et créent des liens de partage.
  :::

  :::u-page-feature
  ---
  icon: i-lucide-users
  to: /administration/users-ldap
  ---
  #title
  Comptes locaux, LDAP et SSO

  #description
  Synchronisation avec votre annuaire, connexion unique via Rocket Auth, rôle administrateur piloté par un groupe.
  :::

  :::u-page-feature
  ---
  icon: i-lucide-lock
  to: /administration/storage#confidentialité
  ---
  #title
  Espaces privés

  #description
  Chacun ne voit que ses fichiers, administrateurs compris. Les contenus sont stockés sous un nom opaque.
  :::
::
