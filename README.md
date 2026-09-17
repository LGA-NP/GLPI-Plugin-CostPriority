# Changelog — Priority Cost

Toutes les versions notables du plugin sont documentées ici, à partir de la
**2.6.6** (dernière version auditée dans `SECURITY_REVIEW.md`).

## [2.9.1] — 2026-09

### Fixed
- **Héritage de configuration inopérant** : la résolution de l'entité
  parente utilisait une méthode GLPI inexistante (`Entity::getAncestors()`).
  Remplacée par la fonction native `getAncestorsOf('glpi_entities', $id)`.
  Cocher « Hériter de l'entité parente » applique désormais bien la
  configuration de la première entité ancêtre non-héritée trouvée en
  remontant l'arbre, au lieu de retomber silencieusement sur l'entité
  racine.

### Known issue (hors plugin)
- Le montant d'un coût de ticket peut s'afficher à **0,00** dans la vue
  d'ensemble d'un budget alors que la ligne de coût elle-même est correcte.
  Il s'agit d'un bug confirmé du cœur de GLPI 11
  ([glpi-project/glpi#24241](https://github.com/glpi-project/glpi/issues/24241)),
  reproductible tous plugins désactivés. Aucune action possible côté
  Priority Cost ; se fier à l'onglet **Coûts** du ticket en attendant un
  correctif GLPI.

## [2.9.0] — 2026-09

### Added
- Le coût s'applique désormais aussi lors du passage direct à **Fermé**
  (`CLOSED`), en plus de **Résolu** (`SOLVED`).
- Un ticket **créé directement** en statut Résolu ou Fermé (import, API,
  règle métier) reçoit maintenant son coût dès sa création — auparavant
  seul un changement de statut sur un ticket existant déclenchait le
  plugin.

### Changed
- `hook.php` : ajout du hook `item_add` en complément de `item_update`,
  tous deux passant par la même liste de statuts cibles.

## [2.8.0] — 2026-09

### Added
- Case à cocher **« Hériter de la configuration de l'entité parente »**
  directement dans l'onglet : cochée, elle verrouille budget et coûts et
  affiche les valeurs effectivement appliquées (résolues depuis le premier
  ancêtre non-hérité) ; décochée, elle permet d'éditer les valeurs propres
  de l'entité. L'entité racine ne peut pas hériter (pas de parent).
- Icône `fas fa-coins` sur l'onglet « Priority Cost ».

### Changed
- **Le formulaire de configuration est désormais directement dans
  l'onglet de l'entité** — suppression du bouton « Configure » et de
  l'écran séparé. `front/config.entity.php` n'est plus qu'un gestionnaire
  de sauvegarde (traitement + redirection vers l'onglet).
- **Le budget est désormais unique par entité** (partagé par les 6
  priorités) au lieu d'un budget par priorité. Nouvelle table
  `glpi_plugin_prioritycost_configs` (`entities_id`, `is_inherited`,
  `budgets_id`).

### Migration automatique
- Ajout de la colonne `is_inherited` sur `glpi_plugin_prioritycost_configs`
  ; toute entité déjà configurée (coûts et/ou budget) est marquée
  `is_inherited = 0` pour préserver exactement le comportement existant
  après mise à jour.

## [2.7.0] — 2026-09

### Security
- *(Déjà corrigé en 2.6.6, reconduit ici)* Contrôle d'accès par entité
  (`Session::haveAccessToEntity()`) dans `front/config.entity.php` et
  `front/entity.tab.php`, empêchant la lecture/écriture cross-entité
  (IDOR) signalée en priorité **haute** dans `SECURITY_REVIEW.md` (#1).
- Validation stricte des priorités à l'écriture (1 à 6 uniquement, toute
  autre clé POST est ignorée) — corrige #2.
- Garde `is_array()` sur `$_POST['cost']` / `$_POST['budget']` avant
  itération — corrige #3.
- Échappement HTML : entièrement supprimé au profit du rendu Twig
  auto-échappant — corrige #4 et #6 en un seul changement.

### Changed
- **Restructuration complète** : classes déplacées de `inc/` vers
  `src/` avec namespace `GlpiPlugin\Prioritycost\…` (autoload GLPI 11) —
  corrige #5.
- Tout le HTML des fronts est désormais rendu via
  `TemplateRenderer` + templates Twig (`templates/*.html.twig`) au lieu de
  `echo` — corrige #6.
- Toutes les chaînes utilisateur portent désormais le domaine de
  traduction `'prioritycost'` — corrige #7. La clé technique interne
  `'Priority Cost'` stockée dans `glpi_ticketcosts.name` reste
  volontairement non traduite (c'est une clé de recherche, pas un
  libellé).
- `setup.php` exécute réellement `install/sql/mysql/install.sql` et
  `uninstall.sql` au lieu d'un schéma dupliqué en dur, et ne supprime
  plus manuellement la ligne `glpi_plugins` (GLPI s'en charge) —
  corrige #8.

### Fixed
- Colonne `cost` passée de `INT` à `DECIMAL(15,2)` : les montants avec
  centimes (le formulaire accepte `step="0.01"`) n'étaient auparavant pas
  stockés correctement.

## [2.6.6] — Base de référence

Version fournie initialement, revue dans `SECURITY_REVIEW.md`. Le
correctif IDOR (#1, priorité haute) était déjà présent ; tous les autres
points du rapport (#2 à #8) restaient ouverts et ont été traités en
2.7.0.
