# DESIGN.md — SchoolSafe (RDC)

> **Nom officiel :** SchoolSafe  
> **Devise :** « Un enfant protégé, un parent informé »  
> **DesignSystem :** SchoolSafeCodex  
> **Cible contextuelle :** Écoles primaires et maternelles en République Démocratique du Congo (smartphones modestes, plein soleil tropical, connectivité intermittente).

---

## 1. Identité & Philosophie Visuelle

SchoolSafeCodex est conçu pour répondre aux contraintes et aux ambitions des environnements scolaires congolais :
* **Accessibilité matérielle :** Conçu d’abord pour les téléphones d’entrée de gamme (Android bas de gamme, écrans LCD fatigués).
* **Lisibilité en extérieur :** Contraste renforcé pour contraster avec la lumière agressive du soleil équatorial.
* **Résilience réseau :** Indicateurs visuels permanents de l'état de synchronisation des données (mode hors-ligne robuste).
* **Sécurité émotionnelle :** Des tons rassurants, un code couleur strict par profil pour éviter toute erreur de manipulation administrative ou parentale.

---

## 2. Palette de Couleurs & Tokens CSS

La palette s'inspire de la nature (forêt tropicale, espoir) et de la noblesse institutionnelle (or).

```css
:root {
  /* Verts institutionnels SchoolSafe */
  --safe-primary: #176b52;
  --safe-dark: #0e4a39;
  --safe-accent: #2e8b6e;
  
  /* Or institutionnel (Valeur, excellence, liseré Admin) */
  --safe-gold: #e9c46a;
  
  /* Vert clair (Enseignant / Succès / Indicateurs actifs) */
  --safe-mint: #7bedaa;

  /* Terracotta (Spécifique profil Parent) */
  --safe-terracotta: #d97757;

  /* Blancs Verre (Glassmorphism modulé) */
  --glass-light-low: rgba(255, 255, 255, 0.13);
  --glass-light-high: rgba(255, 255, 255, 0.22);
  --glass-dark-overlay: rgba(14, 74, 57, 0.85);

  /* États & Alertes */
  --offline-amber: #f39c12;
  --text-main: #ffffff;
  --text-muted: #e0f2fe;
}
```

---

## 3. Le Système "LOGO-EN-FOND" (Dynamique & Organique)

Pour personnaliser l’application aux couleurs de chaque école partenaire tout en garantissant la lisibilité :
1. Le logo officiel de l'établissement enregistré dans la base locale sert de texture d’arrière-plan globale.
2. **Traitement visuel :** Apport d'un flou intense et d'une saturation rehaussée, recouvert d'un voile opaque aux couleurs de la marque.
3. **Respiration organique :** Une animation lente simule un battement subtil pour donner vie à l'interface sans surcharger le processeur (GPU-friendly).

```css
.school-bg-container {
  position: fixed;
  inset: 0;
  z-index: -1;
  overflow: hidden;
  background-color: var(--safe-dark);
}

.school-bg-logo {
  position: absolute;
  inset: -20%;
  background-image: var(--school-logo-url);
  background-size: cover;
  background-position: center;
  filter: blur(60px) saturate(140%);
  opacity: 0.6;
  animation: organic-breath 12s ease-in-out infinite alternate;
}

.school-bg-overlay {
  position: absolute;
  inset: 0;
  background: var(--glass-dark-overlay);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
}

@keyframes organic-breath {
  0% {
    transform: scale(1);
  }
  100% {
    transform: scale(1.05);
  }
}
```

---

## 4. Les 20 Effets & Composants Glassmorphism

Optimisés en CSS pur pour garantir 60 FPS constants sur les téléphones modestes :

1. **Cartes translucides de base :** `background: var(--glass-light-low); border: 1px solid var(--glass-light-high); border-radius: 18px;`
2. **Effet de soulèvement au survol/toucher :** `transition: transform 200ms ease, box-shadow 200ms ease;` -> `.card:active { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.25); }`
3. **Apparition en cascade (Staggered fade-in) :** Délais d'animation CSS calculés par index (`animation: fadeInUp 300ms ease backwards; animation-delay: calc(var(--i) * 60ms);`)
4. **Anneaux de progression SVG :** Indicateurs circulaires fluides pour l'assiduité ou les frais scolaires.
5. **Mini-graphiques intégrés dans les KPI :** Sparklines en SVG pur sans dépendance externe (Chart.js interdit).
6. **Carte sombre à checklist :** Fond opaque `rgba(14, 74, 57, 0.9)` avec bordure dorée fine pour les tâches prioritaires.
7. **Navigation en pilule (Bottom Bar) :** Barre de navigation flottante, coins arrondis, effet de lévitation.
8. **Bande d'événements défilante :** Actualités urgentes de l'école en ticker CSS pur.
9. **Chips de bascule (Filtres rapides) :** Boutons à bascule tactile avec transition d'état instantanée.
10. **Badges d'évolution :** Pastilles avec liseré lumineux pour indiquer le passage de classe ou le statut de paiement.
11. **Ripple circulaire au toucher :** Effet d'onde tactile généré en CSS/JS léger sur les boutons principaux.
12. **Modales flottantes floutées :** Fenêtres surgissantes avec fond sombre opaque à 90% et flou d'arrière-plan.
13. **Champs de saisie "Plein Soleil" :** Inputs avec bordure épaisse de 2px et fond contrasté pour écriture en extérieur.
14. **Squelettes de chargement (Skeletons) :** Animations de balayage linéaire douces pour le chargement des listes d'élèves hors ligne.
15. **Diviseurs lumineux :** Lignes de séparation subtiles avec dégradé transparent -> blanc verre -> transparent.
16. **Toggles de statut (Présent/Absent) :** Curseurs tactiles larges adaptés aux gros doigts et aux écrans réduits.
17. **Tooltips contextuels :** Bulles d'aide à apparition rapide sur appui long.
18. **En-têtes épinglés (Sticky Glass Headers) :** Barres de titre qui conservent le flou en défilant.
19. **Boutons d'action flottante (FAB) :** Bouton rond principal pour l'appel ou l'alerte d'urgence, ombré et accentué.
20. **Compteurs numériques à pastille :** Badges de notification rouge corail/or pour les messages non lus.

---

## 5. Signatures Visuelles par Profil

L'interface adapte instantanément son liseré d'identification selon le rôle de l'utilisateur connecté pour éviter toute confusion lors du passage rapide d'un téléphone d'une main à l'autre :

* 👑 **Administrateur (Directeur / Secrétaire) :**
  * *Signature :* Liseré et accents **Or (`--safe-gold`)**.
  * *Règle CSS :* `.profile-admin { border-top: 3px solid var(--safe-gold); }`
* 🍏 **Enseignant (Titulaire de classe) :**
  * *Signature :* Liseré et accents **Vert Menthe (`--safe-mint`)**.
  * *Règle CSS :* `.profile-teacher { border-top: 3px solid var(--safe-mint); }`
* 🏡 **Parent (Tuteur légal) :**
  * *Signature :* Liseré et accents **Terracotta (`--safe-terracotta`)**.
  * *Règle CSS :* `.profile-parent { border-top: 3px solid var(--safe-terracotta); }`

---

## 6. Le Sourire de Connexion & Résilience Hors-Ligne

La PWA intègre un indicateur permanent d'état réseau situé dans la pilule de navigation supérieure :

* **En ligne (Connecté) :** La pilule affiche un point vert stable (`--safe-mint`). Synchronisation active en arrière-plan.
* **Hors-ligne (Coupure réseau / Délestage / Zone blanche) :** 
  * La pilule s'illumine instantanément en **Ambre (`--offline-amber`)**.
  * Un texte discret affiche : *« Mode autonome actif »*.
  * Les données saisies (absences, notes, présences) sont stockées en base locale (`IndexedDB`) prêtes à être envoyées dès le retour du signal.

```css
.connection-pill {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 50px;
  font-size: 11px;
  font-weight: 600;
  background: var(--glass-light-low);
  border: 1px solid var(--glass-light-high);
  color: var(--text-main);
  transition: background 300ms ease, border-color 300ms ease;
}

.connection-pill.offline {
  border-color: var(--offline-amber);
  background: rgba(243, 156, 18, 0.2);
}

.connection-pill.offline::before {
  content: "";
  width: 8px;
  height: 8px;
  background-color: var(--offline-amber);
  border-radius: 50%;
  margin-right: 6px;
  box-shadow: 0 0 8px var(--offline-amber);
  animation: pulse-amber 1.5s infinite;
}

@keyframes pulse-amber {
  0% { opacity: 1; }
  50% { opacity: 0.4; }
  100% { opacity: 1; }
}
```

---

## 7. Règles d'Or du Développement (CSS Pur & Mobile First)

1. **Zéro Framework Lourd :** Pas de Tailwind lourd, pas de Bootstrap. Un fichier CSS modulaire optimisé (< 15 Ko compressé).
2. **Conformité Contraste Soleil (WCAG AAA) :** Tous les textes sur fond verre ou couleur doivent maintenir un rapport de contraste minimal de **7:1** pour rester lisibles sous un soleil tropical direct.
3. **Mobile First Strict :** Design pensé pour une largeur minimale de 320px (petits smartphones Tecno/Infinix très répandus en RDC).
4. **Performance CPU/GPU :** Utilisation exclusive des propriétés composables (`transform`, `opacity`, `backdrop-filter`) pour éviter les saccades au défilement.