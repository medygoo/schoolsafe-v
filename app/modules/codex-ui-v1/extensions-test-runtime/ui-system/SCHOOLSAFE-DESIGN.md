# SchoolSafe design

```text
DESIGN_SYSTEM=SchoolSafe
DESIGN_VERSION=1.0
STATUS=LOCKED
```

Toute nouvelle interface SchoolSafe doit réutiliser le shell, les tokens, les composants et l’un des modèles officiels. Toute nouvelle variante doit être justifiée par un besoin fonctionnel réel.

The locked shell is `.ss-app`: sidebar, top bar, profile menu, breadcrumbs when the page is nested, and `.ss-content` capped at `--ss-page-max`. Official page models live in `ui-system/templates/`: dashboard, list-table, detail, form, and report. They load `tokens.css`, `components.css`, and `animations.css` only.

Allowed motion is the same on every page: button hover, focus, sidebar, modal, dropdown, loading spinner, and a short block appearance. Duration is `--ss-duration`.

This file is the visual authority for SchoolSafe. Rosario remains the engine: accounts, permissions, menus, and PostgreSQL stay in Rosario. This document does not authorize a business change.

The gallery that shows these rules is `ui-system/visual-system.html`. Colors live only in `ui-system/tokens.css`.

## Identity

SchoolSafe is a school product: calm, trustworthy, and readable by a non-technical staff member. Blue and white carry the interface. Gold is the accent, used as a bar or a rare emphasis, never as body text. The rainbow blocks belong to the logo only.

Tabler can inform structure: sidebar, top bar, cards, tables, clear actions. It does not supply the identity.

## Color

Measured from `assets/themes/SchoolSafe/logo.png`:

- `--ss-primary` `#01268d`, the shield.
- `--ss-primary-light` `#023fbf`, the brighter blue for the active item.
- `--ss-accent` `#f0b515`, the word Safe.
- `--ss-surface` `#fcfcfc`, the word School.
- `--ss-primary-dark` `#011f74`, the shield pressed one step darker. It is not a fourth logo color.
- `--ss-text` `#1a1a1a`.
- `--ss-text-muted` `#5c6775`.
- `--ss-border` `#e4e9ef`.
- `--ss-surface-alt` `#f4f7fb`, the quiet row and panel fill.

Meaning colors, from the logo blocks, and only for status:

- `--ss-success` `#0ba473`, with `--ss-success-bg` and `--ss-success-text` for a badge on a light ground.
- `--ss-warning` `#fc6f06`, with `--ss-warning-bg` and `--ss-warning-text`.
- `--ss-danger` `#e32822`, with `--ss-danger-bg`.
- `--ss-ai` `#7940f3`, with `--ss-ai-bg` and `--ss-ai-text`.

The text tokens on those tints exist so a small badge stays readable. They are not extra brand colors.

Do not add another SchoolSafe color in a screen file. Status must also have a word or an icon.

## Sub-branch color

```text
GLOBAL_IDENTITY = SchoolSafe blue / white / gold
SUB_BRANCH = one stable accent color
PAGE = inherits the accent of its sub-branch
NO_NEW_PAGE_COLOR = YES
SUCCESS = green
WARNING = orange
DANGER = red
```

A sub-branch color is always shown with its name and its icon. The color never carries the meaning by itself. Keep the whole application within 5–7 accents. A new page does not receive its own color: it reuses the accent token of its sub-branch, defined once in `tokens.css`.

The same job keeps the same accent. A different job gets its own accent, still in `tokens.css`. Green, orange, and red stay status colors. Annonces uses `--ss-branch-annonces`. Sondages uses `--ss-branch-sondages`. Calendrier uses `--ss-branch-calendrier`. Périodes uses `--ss-branch-periodes`. Créneaux uses `--ss-branch-creneaux`. Niveaux uses `--ss-branch-niveaux`. Établissement uses `--ss-branch-etablissement`. Paramètres uses `--ss-branch-parametres`. Année uses `--ss-branch-annee`. Journal uses `--ss-branch-journal`. Dossier uses `--ss-branch-dossier`. Inscription uses `--ss-branch-inscription`.

## Type

Titles and the names of sections use Poppins Semibold. The interface, tables, forms, and body use Inter. Lato is the fallback already present in Rosario. Load both from a redistributable web font. Do not commit a proprietary font file.

Body text is 16px. Titles stay short. Do not set long reading text in gold.

## Spacing, radius, shadow

The rhythm is 8px: 4, 8, 16, 24, 32, 48.

Inputs use 8px radius. Alerts and groups use 12px. Cards, tables, and shells use 16px, with a 4px gold top border.

Shadows stay small: a hairline lift, and a slightly larger lift on hover. No wide colored glow.

## Controls

Primary button: primary blue, text `--ss-text-on-primary`. Hover uses primary-dark. Secondary is a white button with a border. Accent gold is rare and uses dark text. Danger is an outline in the danger color, for a destructive action that Rosario already allows.

Every control has a visible focus ring in primary blue. Disabled controls stay visible and do not look like a hidden field. Touch height is at least 44px under 768px. Desktop controls are 40px.

## Forms

One column on a phone. Labels sit above the fields. Help text is muted. An error uses the danger color plus a sentence, not color alone. Required fields stay marked. Do not rename a Rosario field.

## Tables

Keep every column Rosario already shows. Header text is Poppins, 13px, primary blue. Rows hover with the alt surface. On a narrow screen the table scrolls inside its card. Do not drop a column only because the screen is small.

## Cards and KPI

A card is a white surface, border, gold top, 16px radius. A KPI card answers one operational question and shows a real Rosario count when it is later connected. The gallery numbers are specimens, not the live school.

## Navigation

Desktop: a persistent sidebar on primary blue, active item on primary-light, content on the alt surface, top bar on primary. Mobile: the sidebar is closed until the menu button opens it. The menu button stays reachable. Do not remove a Rosario program while restyling the menu.

## Responsive

Under 768px the shell stacks, forms become one column, and the page must not scroll sideways. From 768px to 1279px the sidebar may collapse. From 1280px the sidebar stays open. Content width stops at 1120px.

## Accessibility

Text and its background must stay readable. Focus is visible. Inputs have labels. Buttons say what they do. Status is never color alone. Honor `prefers-reduced-motion`.

## Motion

Allowed: button hover, focus, sidebar open, modal, dropdown, a 4px rise when a block appears, and a loading spinner. Duration is 160ms. The same set is used on every official template. No decorative loops and no page-specific motion.

## Desktop and mobile

Desktop shows the school name, the current section, and the primary action in the top bar. Mobile keeps that action and replaces the open sidebar with the menu button. Both use the same tokens.
