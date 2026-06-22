# Vizuální refresh úvodní stránky index.html (nový vzhled + menu + animace)

**Date:** 2026-06-22
**Owner:** Honza
**Status:** Draft
**Planner:** BaC-planner (basic)

## Goal
Překlopit schválený mockup (vizuál, hamburger menu, ambientní Canvas animace karet) do reálného `index.html`, zachovat všechny texty a sekce beze změny.

## Success criteria
- Nový vzhled + sticky nav s hamburgerem + Canvas animace v hero jsou živé na index.html.
- Všechny původní texty/sekce zůstávají (jen drobná stylistická korektura, žádná změna messagingu).
- Mobil (iPhone 14 Pro Max) bez horizontálního scrollu, hamburger funkční, animace běží.
- Hra v animaci respektuje pravidla štychů (trumf/hodnoty), scény rotují donekonečna.
- Desktop bez regrese. Verze v1.5.0.

## Stakeholders
- Honza – schvaluje vizuál, testuje, rozhoduje o merge/deploy.

## Constraints
- **TEXTY BEZE ZMĚNY** (jen drobná stylistika). Žádné prezentování "16letá".
- Zachovat funkční výběr hráčů + "Spustit hru" a odkaz na záznamník (mobilně dostupný přes hamburger).
- Žádné staging – merge do master = živá produkce (lftp deploy).
- Animace respektuje `prefers-reduced-motion` (statický stůl).
- Font Awesome zůstává z CDN (ikony se používají napříč stránkou).

## Scope
**In:** nav+hamburger, hero+Canvas animace, vizuální sladění existujících sekcí, mobil, levné a11y fixy, verze+CHANGELOG, deploy.
**Out:** změny textů/obsahu, SEO/OG mass přidání, kompletní re-layout všech sekcí, "barvy mass refactor" (dle Miro-replanning nízká priorita pro showcase).

## Plan

### P0: Větev z master
- **Produces:** pracovní větev pro úpravy index.html.
- **Touches:** git.
- **HITL:** ne.
- **Executor:** BaC-builder.

### Chunk A: Nav + hamburger + a11y základ + design tokeny
- **Produces:** sticky nav s hamburgerem (☰↔✕, zavírání link/mimo/Escape, aria), nové CSS proměnné/utility z mockupu (sladěné se stávajícím :root), focus-visible, prefers-reduced-motion, kontrast `--text-muted` zesvětlit.
- **Proves success:** hamburger funguje na mobilu, záznamník dostupný v menu; tab focus viditelný; JS bez chyb.
- **Touches:** `index.html` `<head>`/`<style>`, `<nav>` markup, `<script>`.
- **Constraints:** texty beze změny; nerozbít stávající odkazy (#rules, #about, offline-recorder.html).
- **HITL:** ne.
- **Executor:** BaC-builder.
- **Depends on:** P0.

### Chunk B: Hero + Canvas animace
- **Produces:** hero v novém layoutu se zachovaným game-setupem (výběr hráčů + "Spustit hru") + `<canvas id="fan">` s animačním enginem z mockupu (simulace hry dle pravidel štychů, scéna vějíř, střídání scén, trumf v rohu s obloukovým odletem). Oprava `selectPlayers` bugu (globální `event` → `this`).
- **Proves success:** animace běží a rotuje scény donekonečna (ověřeno virtuálním testem řetězu); štych bere správná karta; "Spustit hru" vede na game.html s počtem hráčů.
- **Touches:** `index.html` hero markup + `<script>` (selectPlayers, animace).
- **Constraints:** zachovat funkci výběru hráčů a startGame; texty hero beze změny.
- **HITL:** ne.
- **Executor:** BaC-builder.
- **Depends on:** A.

### Chunk C: Sladit existující sekce + drobné a11y
- **Produces:** vizuální sladění sekcí (pravidla v kostce, podrobný průvodce, promo záznamníku, story, roadmapa) do nového stylu (karty, spacing, mezery) – TEXTY BEZE ZMĚNY; `aria-hidden` na dekorativní FA ikony; hamburger tap target ≥44px (už v A).
- **Proves success:** sekce vypadají konzistentně s novým hero; obsah identický s původním.
- **Touches:** `index.html` markup/CSS existujících sekcí.
- **Constraints:** nezměnit žádný text; jen třídy/styl/struktura obalu.
- **HITL:** ne.
- **Executor:** BaC-builder.
- **Depends on:** A.

### Chunk D: Mobil
- **Produces:** media-query ladění (≤768/480), hero+animace+sekce na iPhone 14 Pro Max bez horizontálního scrollu.
- **Proves success:** DevTools emulace iPhone 14 Pro Max + on-device kontrola.
- **Touches:** `index.html` `<style>` media queries.
- **HITL:** **[HITL on-device]** vizuální kontrola.
- **Executor:** BaC-builder + Honza.
- **Depends on:** A, B, C.

### Chunk E: Verze + CHANGELOG
- **Produces:** footer v1.5.0 + build-date 2026-06-22, záznam v CHANGELOG.md.
- **Touches:** `index.html` footer, `CHANGELOG.md`.
- **HITL:** ne.
- **Executor:** BaC-builder.
- **Depends on:** A–D.

### Chunk F: Deploy
- **Produces:** commit → PR → merge do master → lftp deploy → ověření produkce (verze + markery + animace).
- **Proves success:** www.stychy.cz ukazuje v1.5.0, nový vzhled, hamburger, animaci; deploy zelený.
- **Touches:** git, GitHub Actions.
- **Constraints:** žádné staging, jde rovnou živě.
- **HITL:** **[HITL]** – potvrzení merge/deploy (živá produkce).
- **Executor:** BaC-builder + Honza.
- **Depends on:** E + [HITL vizuál].

## Ověřování
- JS syntaxe všech `<script>` bloků (`node vm.Script`).
- Virtuální test řetězu animačních scén (běží dál, nepřetrhne se).
- Vizuál: DevTools emulace iPhone 14 Pro Max → on-device.

## Critical path
P0 → A → B → C → D → [HITL vizuál] → E → F([HITL deploy]).
(A je základ; B a C závisí na A, lze dělat za sebou; D po B+C.)

## Definition of done
- Index.html má nový vzhled + menu + animaci, texty beze změny, mobil OK, desktop bez regrese.
- v1.5.0 živé na stychy.cz, deploy zelený, Honza odsouhlasil vizuál.
