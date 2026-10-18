# SchoolSafe : application UI/UX Pro Max

Reference : SCHOOLSAFE_CODEX_UI_UX_PRO_MAX_V1.
Autorisation : application de bout en bout du skill UI/UX Pro Max au projet, dans le perimetre isole Codex defini par le proprietaire. Integration finale reservee a Cursor.

## Sources et arbitrages

Skill local consulte : C:/Users/PC/.codex/skills/ui-ux-pro-max/SKILL.md.
References lues : quick-reference.md (web) et pro-rules.md (les regles natives sont adaptees au web, pas appliquees automatiquement).
Recherche locale executee avec Python -B, sans installation ni ecriture dans le skill :

```text
search.py "school administration dashboard" --design-system --density 8 --variance 3 -p SchoolSafe
search.py "administrative data dashboard" --domain style -n 1
search.py "focus not obscured" --domain ux -n 1
```

Le premier resultat proposait un patron marketing Hero + Features + CTA, non pertinent : il n'a pas ete applique ni persiste comme reference. Le resultat cible `data-dense-dashboard`, categorie BI/Analytics, est adapte aux consultations scolaires. Les recommandations de graphiques, export et bibliotheques ne constituent pas une autorisation d'ajouter des fonctions.
Le resultat Focus Not Obscured Enhanced concerne le critere AAA : il sert de guide de confort clavier, pas de declaration de conformite AA/AAA.
Stack observe : HTML/CSS/JavaScript sans framework, Lucide local, Nunito Sans local pour les titres. Aucun React/Tailwind ajoute ; les directives specifiques aux frameworks non presents sont ecartees.

## Direction retenue

Interface de gestion scolaire claire, dense et calme, pour les adultes qui consultent des dossiers et realisent des operations repetitives. Logo, navigation a deux niveaux, themes blanc et bleu, Vue generale unifiee et personnage JASPE existants sont conserves.

- Texte courant : 1rem ; controles et tables : .875rem ; textes secondaires : minimum .75rem. Les unites rem suivent l'agrandissement du texte.
- Titres : hierarchie courte, Nunito Sans deja embarquee ; pas de police externe chargee.
- Espacements : 4/8/12/16/24/32 px ; controles usuels de 44 px minimum ; tableaux a defilement interne.
- Couleurs semantiques : texte, texte secondaire, surface, fond, accent, bordure de controle, focus, succes, erreur et attente. Les etats gardent leur libelle textuel.
- Deux themes controles independamment ; le theme bleu demeure le theme bleu valide, sans remplacement de marque.
- Pas d'animation decorative ; retour de pression et chargement bref, annules avec mouvements reduits.
- Sections non encadrees et grille de travail ; seuls les elements repetes et outils/modalites gardent leur cadre.
- Formulaires : libelles existants conserves, bordures plus visibles, saisie 1rem, feedback de chargement/erreur harmonise.
- Menus mobiles : contenu arriere inert pendant l'ouverture, boucle clavier sur commandes visibles et retour par Echap. Changement de poste ferme les menus.
- Dossiers/notifications : etat ouvert conserve pendant le chargement et les nouveaux rendus de la synthese.
- JASPE : presentation de la preview et feuille opt-in du markup natif ; aucune modification de moteur, voix, permissions, actions ou animation physique.

## Integration proposee

Charger `app/styles/codex-ui-v1/ui-ux-pro-max.css` apres `codex-ui-v1.css` et `lot2-screens.css`. La preview charge deja cette feuille. Cursor choisira le montage final apres validation.
Charger ensuite `branch-presentation.css` pour les accents de branche et les groupes de commandes. Les decisions et mesures de cette extension sont dans UI_UX_PRO_MAX_BRANCH_PRESENTATION.md; les presentations par fonction restent celles de UI_UX_PRO_MAX_DOMAIN_PRESENTATION.md.
Charger enfin `overview-composition.css` pour la composition du tableau de bord inspiree de la reference du proprietaire. Decisions, donnees derivees et zones non raccordees : OVERVIEW_COMPOSITION_HANDOFF.md.
L'extension des fiches se charge ensuite : `record-presentation.js` avant `lot2-views.js`, `record-presentation.css` apres `overview-composition.css`. Elle conserve les projections par onglet et les brouillons non sauvegardes; voir RECORD_PRESENTATION_HANDOFF.md.
Les ajustements clavier et etat de disclosure sont dans `unified-interface.js` et `lot2-views.js`. Les contrats, permissions et destinations sont inchanges.
JASPE natif : utiliser la feuille `jaspe-overlay.css` uniquement avec l'attribut opt-in `data-codex-ui-v1` sur le dialog existant. Ne pas copier le dialog de demonstration ni substituer le moteur.

## Couverture et limites

15 presentations par poste, 13 branches, 211 destinations. Les 116 consultations specialisees, six lectures de demonstration et deux vues indisponibles beneficient du style commun. Les 87 ouvertures historiques beneficient du cadre visuel du lot ; leur ecran metier final demeure a examiner dans l'integration Cursor.
Le classement Palmares demeure un emplacement reserve, sans calcul de classement.
Tout chiffre de preview est fictif. Aucun backend, authentification, SQL, permission, systeme de carte/scanner/retrait/frais ou moteur JASPE modifie. Aucun commit, push, merge ou deploiement.

La copie n'est pas un depot Git. La branche et le HEAD d'origine proviennent du manifeste du snapshot ; le controle d'integrite se fait sur TREE.sha256 pour les 1017 fichiers, pas sur un depot Cursor ni un distant.
