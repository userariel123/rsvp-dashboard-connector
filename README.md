# RSVP Dashboard Connector

Petit plugin WordPress qui transforme les réponses RSVP collectées via **Fluent Forms Pro** en un dashboard en direct, joli et réutilisable sur n'importe quel site — sans passer par un export Excel,JS/CSS vanille + [Tabler](https://tabler.io/) + [Chart.js](https://www.chartjs.org/) chargés en CDN.

## Ce que ça fait

- Lit les réponses d'un formulaire Fluent Forms Pro (confirmés / déclinés, nombre d'adultes, nombre d'enfants, liste d'invités).
- Affiche un dashboard en direct (cartes de stats, graphique en donut, tableau d'invités avec recherche) via le shortcode `[rsvp_dashboard]`.
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
7. Enregistre.
8. Crée/édite une page Elementor en template **Canvas**, ajoute un widget **Shortcode**, colle `[rsvp_dashboard]`, publie.

## Sécurité

L'endpoint `/wp-json/rsvp-dashboard/v1/stats` (qui alimente le dashboard) contient des données personnelles d'invités (noms, réponses). Il est protégé par un **jeton secret auto-généré** — comme un mot de passe de page, sans nécessiter de connexion WordPress. Le jeton est généré automatiquement au premier chargement et intégré à l'URL utilisée par le dashboard ; tu n'as rien à configurer.

L'endpoint `/wp-json/rsvp-dashboard/v1/debug/{form_id}` est réservé aux administrateurs connectés (`manage_options`).

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

## Licence des dépendances externes

Tabler et Chart.js sont chargés directement depuis un CDN (jsDelivr), sous licence MIT, sans fichiers vendorés dans ce dépôt.
