# Zpracování 13 připomínek k záznamníku Štychy

**Date:** 2026-06-21
**Owner:** Honza (jan.tobolik@sensio.cz)
**Status:** Draft
**Planner:** BaC-planner + BaC-architect design pass
**Branch:** `fix-results-display` (checkout z `origin/claude/fix-results-display-5QZLP`)

## Goal
Zpracovat 13 připomínek k offline záznamníku her Štychy – drobné UX/CSS/JS fixy, mobilní optimalizaci zápisu kola a roast rozšíření statistik.

## Success criteria
- Připomínky 1–9, 11, 12 opravené a vizuálně ověřené.
- Zápis hlášení i výsledků se na iPhone 14 Pro Max (430×932) vejde na jednu obrazovku i s 11 hráči (simple mode).
- Každý hráč ve statistikách dostane právě 1 unikátní ocenění; poslední v tabulce vždy roast.
- Žádná regrese na desktopu (změny mobilu jen v media-query ≤480px).

## Stakeholders
- Honza – rozhoduje „done", testuje na reálném zařízení.
- Hráči Štychů – koncoví uživatelé záznamníku.

## Constraints
- Vše v jediném souboru `offline-recorder.html` (HTML + inline CSS/JS, ~4300 ř.).
- Žádné PR bez HITL (CLAUDE.md). Žádné dlouhé pomlčky v textech (CLAUDE.md).
- Mobilní změny nesmí rozbít desktop layout (>480px beze změny).
- Klávesnicové zkratky (0/1, Enter, Esc) musí zůstat funkční.

## Scope
**In:** všech 13 připomínek (1–13), seskupené do 6 chunků.
**Out:** backend (`api/*.php`), DB schéma, online hra proti AI (`game.html`), merge do `master` (řešíme samostatně po dokončení).

## Cross-cutting prerequisite (dělá se PRVNÍ, před chunky)
**P0 – Oprava nedefinovaných CSS proměnných** v `:root` (~ř. 9):
```css
--primary: var(--accent);
--card-bg: var(--surface);
--text: var(--text-main);
```
Tím se automaticky opraví průhledná/bílá pozadí u **item 1** (autocomplete) a **item 7** (select), plus latentní bugy v `.bid-input-row.current-input` a `.stats-section-title`.

⚠️ **[Critical – replanning] Desktop regrese:** nastavení `--primary` zviditelní accent na ř. **307** (`.stats-section-title`), **336** (`.stat-player`), **489/491** (hover čísla hráče), **714** (`.bid-input-row.current-input` border), **1008** (`.ranking-row .score`), **1396** (select). Pravděpodobně to *obnovuje zamýšlený* vzhled (autor `--primary` myslel jako accent), ale **PO P0 povinně vizuálně zkontrolovat desktop na těchto místech** (statistiky, ranking, bidding highlight) – součást [HITL vizuál] gate.

---

## Plan

### Chunk A – Rychlé fixy: CSS & texty (item 1, 5, 7)
- **Produces:** plné pozadí dropdownů, správný popisek tlačítka.
- **Proves success:** dropdown hráčů i select „Počet karet" mají plné tmavé pozadí (ř. 534, 1396); tlačítko zní „Zadat výsledky kola" (ř. 3007).
- **Touches:** `:root` (P0), `.autocomplete-list` (+ box-shadow pro oddělení), `#edit-cards-count` (+ stylované `<option>`), text na ř. 3007.
- **Constraints:** jen CSS/text, žádná logika.
- **HITL gate:** ne (vizuální kontrola po dávce).
- **Executor:** BaC-builder.
- **Depends on:** P0.
- **Rollback:** git revert dílčího commitu.

### Chunk B – Rychlé fixy: JS logika (item 2, 3, 6b, 12)
- **Produces:** chytřejší autocomplete, nezávislý výběr balíčků, správné pořadí výsledků, ESC na modálech.
- **Proves success:**
  - **2:** už zadané jméno se v jiných pozicích nenabízí (`showAutocomplete`, ř. 3917 – vyloučit hodnoty ostatních `player-name-*`).
  - **3:** změna počtu hráčů nezruší výběr balíčků (`selectPlayerCount` ř. 2683 – zúžit selektor jen na kontejner hráčů, ne `.player-count-btn` globálně).
  - **6b:** pořadí ve výsledcích podle aktuálního kola, rozdávající poslední (`openResultsModal` ř. 3525 – použít stejnou rotaci jako bidding ř. 3259–3267).
  - **12:** Esc zavře libovolný otevřený modál (globální `keydown` listener nad `.modal.show` / podle stávajícího `openModal`/`closeModal`).
- **Touches:** `showAutocomplete`, `selectPlayerCount` (+ id na kontejner hráčů), `openResultsModal`, globální key handler.
- **Constraints:** nesmí rozbít stávající klávesové ovládání (0/1/Enter).
- **HITL gate:** ne.
- **Executor:** BaC-builder.
- **Depends on:** P0 (kvůli id kontejneru u item 3 – jinak nezávislé).
- **Parallel-safe with:** Chunk A (jiné funkce/řádky).
- **Rollback:** git revert.

### Chunk C – Trumf zobrazení (item 8, 9)
- **Produces:** trumf viditelný ve formuláři výsledků (8) a ve sloupci „Karty" v tabulce kol (9).
- **Proves success:**
  - **9:** `<td>` na ř. 2895 ukazuje vedle počtu karet i trumf kola (`round.trump_suit/value` + `getSuitSymbol` + `.trump-display`).
  - **8:** results modal ukazuje trumf aktuálního kola (`currentRound.trump_suit/value`).
- **Touches:** render tabulky (ř. 2895), `openResultsModal`/results modal markup, reuse `getSuitSymbol` (ř. 4275) + `.trump-display`.
- **Constraints:** ošetřit `trump_suit === 'none'`.
- **HITL gate:** ne.
- **Executor:** BaC-builder.
- **Depends on:** P0.
- **Parallel-safe with:** A, B.
- **Rollback:** git revert.

> Poznámka: A + B + C + (P0) tvoří první dávku „rychlé výhry" – jeden review a jedno vizuální ověření. Item 8 částečně překrývá metaplán D (kontextový proužek v modálu) – sladit při implementaci D.
>
> ⚠️ **[Important – replanning] „Parallel-safe" znamená logicky nezávislé, NE literálně paralelně.** Všechny chunky editují stejný soubor → dělat **sekvenčně P0 → A → B → C** v jedné session (jeden agent), ne paralelními worktrees. Jinak hrozí edit konflikty (zejm. Esc handler v B a trumf v C oba sahají do modal/JS oblasti).

---

### Chunk D – METAPLÁN: Mobilní layout zápisu kola (item 4, 6, 6c, 13)
Design dle BaC-architect (verdict: needs-revision = design hotový, ale je to víc než CSS).

**D.1 Spacing/font scale** – media-query `@media (max-width:480px)`:
| Token | Desktop | Mobil ≤480px |
|---|---|---|
| `.modal` padding | 20px | 6px |
| `.modal-content` padding | 30px | 12px |
| `.modal-content` max-height | 90vh | 96vh |
| řádek vertical padding | 6px | 4px |
| klíčový údaj (karty/trumf/sázka) | 1.3rem | 1.6–2rem |
| helper text | 0.85rem | skrytý |

**D.2 Bidding modal:** skrýt helper `<p>`; info bar přestavět na kompaktní proužek **Karty · Trumf · Rozdávající** (trumf + rozdávající dnes v modálu CHYBÍ → JS přidání v `openBiddingModal` ~ř. 3253); `.bid-btn` 36→32 px, gap 5→4, `flex-wrap:nowrap; overflow-x:auto` jako pojistka.

**D.3 Results modal (item 6, 6c):** skrýt helper + klávesovou nápovědu na mobilu; nový layout `.result-row` = **jméno | velký badge sázky | tlačítka ✗/✓**:
- `.bid-badge` – velké číslo nahlášených štychů (1.9rem, `var(--warning)`) do prázdného místa (item 6c); číslo SOUČASNĚ odebrat z `.player-bid` (ať není dvakrát).
- `.result-btn` – pevná velikost 52×44 px (≥44px touch target dle Apple HIG), `padding:0`, výška řízená boxem ne paddingem → **větší dotyk, stejná výška řádku** (item 6).
- Z textu odebrat „0"/„1" (item 6 – jen ✗/✓), legenda zůstává, klávesnice 0/1 funguje. **[replanning] Hlasový parser čte přepis řeči, ne `button.textContent` → bezpečné; po změně smoke-test hlasem.**

**D.4 Tabulka kol:** stejná scale, horizontální scroll jako fallback.

**D.5 [Critical – replanning] Skrývání helper textů:** helper `<p>` a klávesová nápověda mají **inline styly** (ř. 1342/1385/1388), které třída nepřebije. Nutno `@media (max-width:480px){ .mobile-hide{ display:none !important; } }` (s `!important`).

**D.6 Verifikace:** nejdřív Chrome DevTools device emulace iPhone 14 Pro Max (430×932), pak `[HITL on-device]` na reálném iPhonu – 11 hráčů v simple i detailed módu.

- **Produces:** zápis hlášení i výsledků na 1 obrazovku iPhone 14 Pro Max i s 11 hráči (simple mode).
- **Proves success:** on-device test 11 hráčů v simple i detailed módu; ✗/✓ se pohodlně ťukají; řádků ≥11 bez scrollu v simple módu.
- **Touches:** `<style>` (nový media block + `.bid-badge`, `.result-btn`, `.mobile-hide` třídy na ř. 1342/1385/1388), `openBiddingModal` (kontext strip), `renderResultsInputs` (ř. 3589–3604).
- **Constraints:** desktop beze změny; zachovat scroll v `.modal-content`; iOS dynamic toolbar → 96vh ne 100vh; klávesnice funkční.
- **HITL gate:** **[HITL]** – odsouhlasit vizuál na reálném zařízení před uzavřením.
- **Executor:** BaC-builder (implementace) + Honza (on-device review).
- **Depends on:** P0, ideálně po Chunk C (kvůli trumfu v modálu – item 8).
- **Rollback:** media-query je izolovaný blok → revert bez dopadu na desktop.
- **Rizika:** detailed-mode pro 11 hráčů může přesto scrollovat (akceptováno); native `<select>` option list nejde stylovat (mimo scope).

---

### Chunk E – METAPLÁN: Roast statistiky (item 10 + 11)
Design dle brainstormu (A/B/C potvrzeno) + BaC-architect (verdict: ready).

**E.1 Award pool – 14 ocenění** (8 roast + 6 pozitivní), každé `{id, emoji, label, desc, kind, strengthFn(p, all)→0..1}`:
- Roast: 🥄 Dřevěná vařečka (poslední místo – *backstop*), 🕳️ Černá díra (nejhorší kolo), 🧱 Cihla (nejnižší úspěšnost), 💔 Zklamání (nejvíc nesplněno), 🐷 Hamoun (hlásí moc, urve málo), 🐔 Zbabělec (nejvíc nul), 🎢 Horská dráha (rozkmit skóre), 🎲 Vabank (nejvyšší jednorázová sázka).
- Pozitivní: 🎯 Odstřelovač (úspěšnost), 🧊 Ledový klid (nejvíc splněno), 🎰 Vysoká hra (nejvíc hlášeno), 🌾 Sklízeč (nejvíc uhráno), 👑 Šampion (1. místo), 📈 Stoupající forma (nejlepší kolo).

**E.2 Normalizace:** min-max přes aktuální hráče (flat metrika → 0 pro všechny → v krátkých hrách nevyhrává); rank-based pro Vařečku a Šampiona (extrém = 1.0).

**E.3 Greedy unikátní přiřazení:**
1. Spočítat matici sil (normalizované `strengthFn`).
2. **Poražený první:** najít hráče s nejnižším `total_score` (tiebreak: vyšší failCount, pak position) → přiřadit nejsilnější roast, který u něj vychází (🥄 Vařečka je vždy assignable).
3. Sestavit kandidáty (síla, award, hráč) pro nepoužité dvojice, seřadit desc (tiebreak award.id asc, position asc – plně deterministické), greedy proházet.
4. Fallback 🃏 generic award (nereachable když pool ≥ hráči; 14 ≥ 11 → vždy úplné párování).

**E.4 Render:** sekce „Ocenění" = **karta na hráče** (emoji + titul + číslo, které ho vyneslo, např. „💔 Zklamání – 5× nesplnil"). Reuse `.stats-grid`/`.stat-card`.

**E.5 Item 11 – náhrada mrtvé metriky:** „Kol, kde všichni splnili" (vždy 0) → **„Nejvíc přehlášené kolo"** = kolo s max `(součet sázek − počet karet)`; počítat v existující smyčce `rounds.forEach` (`roundBidSum` ř. 4036).

- **Produces:** každý hráč 1 unikátní titul, smysluplná overview metrika.
- **Proves success:** syntetická hra 11 hráčů / 2 kola → každý dostane jinou kartu, poslední ukazuje roast emoji; žádné „vždy 0" pole.
- **Touches:** `calculateGameStats` (ř. 3980–4097 – přidat `total_score` do stat objektu **podle position**, ne name; přidat `maxOverbid`; odebrat staré single-winner sorty), nové fce `getAwardPool()` + `assignAwards()`, `renderStats` sekce 1 (ř. 4116) a sekce 2 (ř. 4133–4159).
- **Constraints:** klíčovat vše podle `position` (jména se můžou opakovat); deterministický výstup pro stejnou hru.
- **HITL gate:** **[HITL]** – odsouhlasit sadu názvů/tonalitu roast titulů.
- **Executor:** BaC-builder.
- **Depends on:** nezávislé na D (jiná část kódu), může běžet paralelně.
- **Rollback:** git revert; staré `renderStats` zachováno v historii.
- **Rizika:** `total_score` je na `players`, ne `playerStats` – **[Important – replanning, potvrzeno 2 reviewery] hlavní implementační past**: v init smyčce `calculateGameStats` přidat `playerStats[p.position].total_score = p.total_score` (klíčovat podle position, ne name). Prázdný stav (0 kol) už ošetřen `alert` + return na ř. 3970.

---

## Critical path
P0 → (A ∥ B ∥ C, jedna dávka „rychlé výhry") → **[HITL vizuál]** → D (mobil, [HITL on-device]) → E (statistiky, [HITL názvy]).
D a E jsou navzájem nezávislé (různé části souboru) → lze prohodit/paralelizovat. Doporučené pořadí dle rozhodnutí: rychlé výhry → D → E.

## Definition of done
- Všech 13 připomínek ověřeno (vizuálně / on-device / syntetická data u statistik).
- Desktop bez regrese (zejm. místa dotčená P0 – viz prerekvizita).
- **[Minor – replanning] Version bump** v footeru (ř. 1189–1191): v1.3.4 → v1.3.5 (drobné) nebo v1.4.0 (mobilní redesign + statistiky) + aktualizovat `build-date`.
- Honza odsouhlasí finální stav; teprve poté řešíme merge `fix-results-display` → `master` (samostatné HITL rozhodnutí, mimo tento plán).

## Replanning log
- BaC-architect design pass (2026-06-21): metaplán D = needs-revision (trumf/rozdávající nutno přidat JS, ne jen CSS); metaplán E = ready. Oba zapracovány výše.
- Cross-cutting P0 (CSS proměnné) přidáno jako prerekvizita – vyřešeno z architect zjištění.

### Miro-replanning (2026-06-22)
**Reviewed by:** Codebase Alignment, Feasibility & Risks, Best Practices, Fresh Perspective
**Findings:** 2 Critical, 3 Important, 2 Minor (po odfiltrování no-context šumu)
**Changes applied:**
- [Critical] P0: přidán povinný desktop-regrese check (ř. 307/336/489/491/714/1008/1396) do [HITL vizuál].
- [Critical] D.5: `.mobile-hide` musí mít `display:none !important` (inline styly nepřebije třída).
- [Important] Zrušeno „parallel-safe", chunky se dělají sekvenčně P0→A→B→C v jedné session.
- [Important] Item 6: ověřovací poznámka že odebrání „0/1" neovlivní hlasový parser (čte transcript, ne textContent).
- [Important] E: zvýrazněno threading `total_score` podle position + prázdný stav ošetřen.
- [Minor] DoD: version bump + build-date.
- [Minor] D.6: verifikace přes DevTools emulaci iPhone 14 Pro Max → on-device.
**Dismissed (reviewer neměl kontext brainstormu):** „roast nedefinovaný", „item 11 nejasný", „item 6b neúplný" – v plánu jsou plně rozepsané. „Deck tlačítka nesdílí třídu" – mylné, sdílí `.player-count-btn` (ověřeno).
