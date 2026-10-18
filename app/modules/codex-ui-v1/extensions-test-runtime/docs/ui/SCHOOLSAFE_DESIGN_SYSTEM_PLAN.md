# SchoolSafe design system plan

This is a plan. No Rosario theme, CSS or PHP file was changed.

## Brand source

The SchoolSafe logo is the source. Colors below are medians measured on that image, not estimated.

- brand-primary `#01268D`: shield face
- brand-secondary `#023FBF`: brighter blue in the shield
- brand-accent `#F0B515`: the word Safe
- text-on-brand `#FCFCFC`: the word School
- success `#0BA473`: green block in the mark
- warning `#FC6F06`: orange block in the mark
- danger `#E32822`: red block in the mark
- ai `#7940F3`: violet block in the mark, reserved for JASPE later

The rainbow arcs stay in the logo. They are not interface colors. Gold is the accent, not a text color on white. Body text stays near-black for readability.

Blue and neutral surfaces dominate. Gold is an accent. Red, green, orange and violet communicate state, not decoration.

## Typography

- Titles: Poppins Semibold or Bold
- Interface, tables and forms: Inter Regular, Medium or Semibold

Both families are published under the SIL Open Font License and are available as web fonts. Do not commit proprietary font files. Load them from a legitimate OFL source, subset the weights actually used, and check phone rendering before adoption.

## Rhythm

Use an 8px spacing rhythm. Radius, shadow and grid stay restrained. Suggested breakpoints already match the live Rosario theme: stacked address tables under 1270px, and the shell collapses under 874px. SchoolSafe breakpoints may be 768 and 1280 if a later lot proves they fit the same shell.

## Components to draw once

Application shell, sidebar, topbar, page header, dashboard card, standard card, data table, compact table, form section, text input, select, date, checkbox, radio, search, filters, primary button, secondary button, destructive button, icon button, badge, tabs, modal, confirmation, toast, inline alert, empty state, loading state, error state, pagination, avatar menu, mobile navigation.

## Responsive evidence

Desktop 1440: persistent dark sidebar, module list, selectors, content. Mobile 390: sidebar hidden behind a menu button, top icons for the collapsed menu, print, help and logout, dashboard cards in one column. Login controls do not move off screen.

## Penpot

File `Nouveau fichier 1`. Page `Page 1` still holds only the rectangle `MCP-CONNECTION-TEST` (300 by 180 at 80,80). That rectangle was not modified.

A separate page, `SchoolSafe Design System`, holds five structure boards: `01 Tokens`, `02 Typography`, `03 Shell desktop`, `04 Shell mobile`, `05 Components`. The live shell now uses the measured logo colors above. No Rosario screen was redesigned in Penpot.
