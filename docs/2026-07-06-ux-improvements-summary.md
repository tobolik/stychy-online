---
title: UX opravy záznamníku (v1.7.0) – souhrn a ověření
owner: Honza
status: hotovo v dockeru, čeká na HITL merge
version: 1.7.0
created: 2026-07-06
updated: 2026-07-06
tags: [stychy, ux, bugfix, soft-delete, handoff]
konvence: BaC-patterns/harness/handoff-format
---

# UX opravy záznamníku Štychy (v1.7.0)

Dávka oprav ovládání záznamníku (`offline-recorder.html` + `api/rounds.php`), nasbíraná Honzou při reálné hře. Vše implementováno a ověřeno v dev dockeru; **nic nenasazeno na produkci** (čeká na ruční merge – deploy = merge do master = živě).

## Co se změnilo (a proč)

| # | Změna | Proč |
|---|---|---|
| 1 | **Duplicitní kola při dvojkliku – opraveno** | Reálný bug ze hry: rychlé kliky na „Pokračovat k hlášení" vytvořily 2-3 kola. |
| 2 | Našeptávač jmen: klávesová navigace (šipky/Enter/Esc) | Nešlo vybrat hráče z klávesnice. |
| 3 | Layout: kompaktní 2řádkový dealer blok, širší herní modály na desktopu, větší písmo | Moc místa po stranách na desktopu; dealer info na 4 řádky. |
| 4 | Audio hláška rozdávajícímu, kolik štychů nesmí zvolit | Pomoc při zápisu (pravidlo: součet sázek ≠ počet karet). |
| 5 | Sticky tlačítko Uložit (hlášení i výsledky) | U 11 hráčů bylo tlačítko pod fold. |
| 6 | Průběžné pořadí: ~1.6× větší písmo, skóre hned vedle jména | Špatná čitelnost, čísla daleko vpravo. |

### Detail #1 (kritický) – tři vrstvy obrany
- **Klient** (`createRound`): in-flight flag + disable tlačítka po dobu requestu.
- **Server idempotence** (`rounds.php` create): pokud poslední nesmazané kolo má status `bidding` (bez zadaného hlášení), vrátí ho místo vytvoření nového; navíc fallback v `catch` při souběhu (druhý INSERT narazí na `UNIQUE(game_id, round_number)` a vrátí existující kolo).
- **Úklid**: znovupovoleno mazání kola, ale **jen posledního a prázdného** (status `bidding`) – soft-delete přes `valid_to`, bez odečtu skóre.

## Nálezy z review (ce-code-review 4 agenti + codex adversarial) – opraveno

- **P0 – po smazání kola nešlo vytvořit nové.** `UNIQUE(game_id, round_number)` nezohledňuje `valid_to`, takže soft-smazané kolo dál blokovalo své číslo → další `create` narazil na kolizi a vracel HTTP 500 (přesně hlavní scénář PR). **Fix:** při soft-delete uvolnit `round_number` – nastavit ho pod aktuální `MIN(round_number)` dané hry (zaručeně unikátní i při opakovaném smaž+vytvoř). Čtení kol stejně filtrují přes `valid_to`. Reprodukováno a ověřeno před/po.
- **P2 – Escape v našeptávači zavíral celý modál** (ztráta zadaných jmen). Fix: `stopPropagation()` v Escape větvi, aby event nebublal na globální handler.
- **P3** – doplněn `AND r.valid_to IS NULL` na 4 cesty (`save_bids`/`save_results`/`get`/`update_results`), aby nešlo operovat na soft-smazaném kole; skloňování „štychů" pro forbidden=0; našeptávač zvýrazní první položku hned na ArrowDown; sticky lišta – desktop margin override na širší `.modal-game` padding; zjednodušen `createRound` trigger; footer build-date; smazáno mrtvé CSS (mini-card, cards-count-big); 2 em dash → krátká pomlčka.

### Codex adversarial – navíc opraveno
- **P1 (souběh delete vs save_bids):** delete kontroloval `status='bidding'` jen před transakcí; finální UPDATE status nekontroloval → při souběhu mohl smazat kolo, které mezitím dostalo hlášení. Fix: `AND status = 'bidding'` i ve finálním UPDATE + kontrola počtu řádků (0 → 409, nemazat).
- **P2 (konzistence):** catch fallback v `create` nevracel zvolený trumf. Fix: doplněn `UPDATE trump_suit/value` i ve fallbacku.

### Otevřené (mimo scope této dávky, k rozhodnutí)
- **Pre-existing P1:** server v `save_bids` zákaz dealerova dorovnání (součet sázek = počet karet) jen varuje, nevynucuje – klient sice tlačítko disabluje, ale ruční POST by neplatné hlášení uložil. Dosud řešeno na klientovi (viz CLAUDE.md). Doporučení: zvážit serverové odmítnutí. NEmění se v této dávce (pre-existing, mimo scope, dopad self-only).

## Jak bylo ověřeno
- **E2E v dev dockeru** (MariaDB 11.8 s kopií produkčních dat, web `localhost:8088`): 3 souběžná `create` → 1 kolo; smaž prázdné → 0; delete→create funguje (i opakované cykly); operace na soft-smazaném kole odmítnuty; plná hra (4 kola + finish).
- **Playwright screenshoty** na mobilu 430×932 i desktopu (trumf, hlášení, výsledky, průběžné pořadí, sticky Uložit u 11 hráčů) + voice text zachycen (`speak()`).
- `php -l` (rounds.php) + `node`/`vm.Script` (JS bloky) čisté; 0 em dash v diffu.

## Stav a další krok
- Větev `ux-improvements` (7 commitů), pushnutá na origin, verze v1.7.0 + CHANGELOG.
- **Čeká na HITL:** review PR a merge do master (spustí deploy). Nic z toho nebylo nasazeno bez dohledu.
- Dev docker (`docker-compose.dev.yml`, přepnutý na MariaDB 11.8 kvůli věrnosti prod) zůstává k dalším testům.

## Kontext pro budoucnost
- Soft-delete model (`valid_to`, nikdy hard-delete) platí i pro kola – proto úklid duplicit řeší soft-delete + uvolnění `round_number`, ne fyzické mazání.
- `UNIQUE(game_id, round_number)` je „globální" (ignoruje `valid_to`) – každý budoucí kód, který soft-maže kolo a chce znovupoužít číslo, musí slot uvolnit (viz delete handler).
