# RSVP Dashboard Connector

Petit plugin WordPress qui transforme les réponses RSVP collectées via **Fluent Forms Pro** en un dashboard en direct, joli et réutilisable sur n'importe quel site — sans passer par un export Excel,JS/CSS vanille + [Tabler](https://tabler.io/) + [Chart.js](https://www.chartjs.org/) chargés en CDN.

## Ce que ça fait

- Lit les réponses d'un formulaire Fluent Forms Pro (confirmés / déclinés, nombre d'adultes, nombre d'enfants, liste d'invités).
- Affiche un dashboard en direct (navbar, cartes de stats avec badges, graphique en donut, tableau d'invités avec recherche + filtre) via le shortcode `[rsvp_dashboard]`.
- **Confirmés/Déclinés = nombre de personnes** (somme adultes+enfants), pas le nombre de réponses reçues — le chiffre qui compte pour un traiteur.
- **Colonnes supplémentaires libres** (jusqu'à 5) : régime alimentaire, table, téléphone... n'importe quel champ additionnel de ton formulaire, affiché dans le tableau.
- **Corbeille** : supprime une réponse depuis le dashboard (icône poubelle), elle passe dans la corbeille Fluent Forms elle-même (pas de suppression définitive) — restaurable depuis le dashboard ou depuis Fluent Forms directement, les deux partagent le même statut.
- Se met à jour automatiquement toutes les 20 secondes, sans recharger la page.
- Le mapping des champs (quel champ du formulaire = prénom / nom / présence / adultes / enfants) est **entièrement configurable depuis l'admin**, car la structure du formulaire change d'un site client à l'autre.
- Les couleurs se changent en éditant une seule variable CSS, sans build ni recompilation.

## Prérequis

- WordPress
- [Fluent Forms Pro](https://fluentforms.com/) actif (nécessaire pour `fluentFormApi()`)
- Elementor (recommandé, pour poser le shortcode sur une page en template **Canvas** — évite les conflits de style avec le thème)

## Installation

1. Zippe le dossier `rsvp-dashboard-connector/`.
2. Dans WordPress : **Extensions → Ajouter → Téléverser une extension**, sélectionne le zip, puis **Activer**.
3. Va dans **Réglages → RSVP Dashboard**.
4. Choisis le formulaire Fluent Forms à suivre.
5. Remplis le mapping des 5 champs (prénom, nom, présence, adultes, enfants) avec les clés exactes de ton formulaire.
   - Pas sûr des clés exactes ? Utilise le lien "Voir un exemple de réponse brute" affiché sous le formulaire de réglages une fois un formulaire sélectionné — il ouvre `/wp-json/rsvp-dashboard/v1/debug/<form_id>` (réservé aux admins) et montre les données brutes d'une vraie réponse.
6. Indique la valeur qui signifie "présence confirmée" (ex: `Oui`).
7. (Optionnel) Renseigne un "Titre du dashboard" (ex: `Yoela & Shalev — RSVP`), affiché dans la barre en haut du dashboard. Vide = nom du site par défaut.
8. (Optionnel) Remplis jusqu'à 5 "Colonne libre" (étiquette + clé exacte) pour afficher des champs supplémentaires du formulaire dans le tableau (régime, table, téléphone...).
9. Enregistre.
10. Crée/édite une page Elementor en template **Canvas**, ajoute un widget **Shortcode**, colle `[rsvp_dashboard]`, publie.
11. (Recommandé) Protège la page elle-même : dans les réglages de la page WordPress/Elementor → **Visibilité → Protégé par mot de passe** (fonctionnalité native de WordPress, aucun réglage dans le plugin) — donne ce mot de passe à qui doit voir le dashboard (ex: les mariés).

## Sécurité

L'endpoint `/wp-json/rsvp-dashboard/v1/stats` (qui alimente le dashboard) contient des données personnelles d'invités (noms, réponses). Il est protégé par un **jeton secret auto-généré** — comme un mot de passe de page, sans nécessiter de connexion WordPress. Le jeton est généré automatiquement au premier chargement et intégré à l'URL utilisée par le dashboard ; tu n'as rien à configurer.

L'endpoint `/wp-json/rsvp-dashboard/v1/debug/{form_id}` est réservé aux administrateurs connectés (`manage_options`).

Les endpoints `/wp-json/rsvp-dashboard/v1/entries/{id}/trash` et `/entries/{id}/restore` (utilisés par le bouton corbeille) sont protégés par le même jeton que `/stats`.

La page elle-même (pas juste l'API) reste à protéger via la fonctionnalité native de WordPress — voir étape 11 de l'installation.

## Structure du projet

```
rsvp-dashboard-connector.php     Bootstrap du plugin
includes/
  class-settings.php             Page de réglages (choix du formulaire + mapping des champs)
  class-rest-api.php             Endpoints REST /debug et /stats
  class-shortcode.php            Shortcode [rsvp_dashboard] + chargement de Tabler/Chart.js
assets/
  css/dashboard.css              Couleurs (variables CSS Tabler) et mise en page
  js/dashboard.js                Récupération des données (toutes les 20s), graphique, tableau, recherche
templates/
  dashboard-markup.php           Gabarit HTML du dashboard (cartes, graphique, tableau)
```

## Réutiliser sur un autre site

1. Installe le même plugin sur le nouveau site.
2. Choisis son formulaire et remplis à nouveau le mapping (la structure du formulaire peut être différente d'un site à l'autre).
3. Colle `[rsvp_dashboard]` sur une page.

Aucune modification de code n'est nécessaire d'un site à l'autre.

## Limites connues

- Les réponses sont paginées par lots de 200 (jusqu'à 5000 réponses au total) — au-delà, contacter pour augmenter la limite.
- L'intégration SMS/WhatsApp n'est pas incluse dans cette version ; le point d'extension naturel est `RSVP_Dashboard_Rest_Api::get_stats()` dans `includes/class-rest-api.php`.
- La mise à la corbeille et la restauration écrivent directement dans la table interne `fluentform_submissions` de Fluent Forms (colonne `status`, valeur `trashed`) ; ce couplage n'est pas garanti par une API publique documentée — si cette fonctionnalité cesse de fonctionner après une mise à jour majeure de Fluent Forms, il faudra revérifier ce schéma.

## Licence des dépendances externes

Tabler et Chart.js sont chargés directement depuis un CDN (jsDelivr), sous licence MIT, sans fichiers vendorés dans ce dépôt.
