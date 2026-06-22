# Kontaktní formulář na webu Štychy (zájem o přístup)

**Date:** 2026-06-22
**Owner:** Honza
**Status:** Draft
**Planner:** BaC-planner (basic)

## Goal
Přidat na web kontaktní formulář (AJAX, bez přenačtení), kterým můžou zájemci o záznamník/přístup napsat Honzovi; e-maily chodí na honza@tobolik.cz.

## Success criteria
- Na login.html je text „v případě zájmu mě kontaktujte" + funkční kontaktní formulář (jméno, e-mail, zpráva).
- Odeslání přes AJAX bez přenačtení, čistý feedback (úspěch/chyba), design laděný s appkou.
- Backend pošle e-mail na honza@tobolik.cz; má validaci a honeypot anti-spam.
- Diskrétní odkaz „Kontakt" ve footeru index.html (vede na login.html#kontakt).
- Nasazeno na stychy.cz.

## Stakeholders
- Honza – příjemce e-mailů, schvaluje, testuje doručení na produkci.
- Zájemci o přístup – odesílatelé.

## Constraints
- PHP `mail()` (jako ostatní weby tobolíka, vzor NetWalking) – cíl honza@tobolik.cz.
- Konzistentní s designem (sdílené `--bg-color #1a1c2c`, `--accent #00ffcc`).
- Doručení e-mailu jde ověřit **až na produkci** (docker nemá MTA).
- Deploy jen z master přes lftp, žádné staging.
- Nezasahovat do rozpracovaného `index-visual-refresh` (jiná větev) – footer odkaz přidat až po/odděleně.

## Scope
**In:** PHP endpoint, kontaktní sekce na login.html, AJAX, footer odkaz, deploy.
**Out:** ukládání zpráv do DB, CAPTCHA (stačí honeypot), uživatelská registrace (záznamník zatím není pro veřejnost).

## Prior art
Vzor: `c:/weby/NetWalking/send_email.php` – AJAX `mail()` (validace `filter_var`, `strip_tags`, echo success/error). Štychy zatím žádný formulář nemají.

## Plan

### P0: Větev — JEDNA společná [replanning]
- **Vše implementovat na větvi `index-visual-refresh`** (vizuál + kontaktní formulář + footer odkaz) → jeden deploy v1.5.0, odpadá merge konflikt footeru index.html (Critical-4). Samostatná větev contact-form se NEDĚLÁ.
- **Executor:** BaC-builder.

### Chunk A: Backend `api/contact.php` [replanning: bezpečnostně zpevněno]
- **Produces:** PHP endpoint **podle vzoru `api/auth.php`** (NE NetWalking `echo`): `handleError`+`handleShutdown`, CORS hlavičky, `require helpers.php`, `getJsonInput()`, `jsonResponse()`. Přijme JSON (name, email, message, honeypot), serverově validuje (prázdná pole, `filter_var` e-mail, délky: name ≤100, message ≤5000), zahodí spam (honeypot), pošle `mail()` na honza@tobolik.cz, vrátí JSON.
- **Bezpečnost (Critical 1,2 + Important):**
  - `From: noreply@stychy.cz` (pevné, doména hostingu kvůli SPF) – **ne** uživatelský e-mail.
  - `Reply-To: <odesílatel>` (aby šlo odpovědět).
  - **Odmítnout CRLF** v e-mailu (`preg_match('/[\r\n]/', $email)` → chyba).
  - `Content-Type: text/plain; charset=UTF-8`, `X-Mailer`.
  - Základní rate-limit (session/IP, ~60 s) proti spamu.
  - Ošetřit `mail()===false` → JSON chyba + `error_log`.
- **Proves success:** lokálně JSON pro validní/nevalidní vstup; produkce: dorazí e-mail.
- **Touches:** `api/contact.php`, `includes/helpers.php` (reuse).
- **Constraints:** žádné DB; žádná header injection; `display_errors=0`.
- **HITL:** ne. **Executor:** BaC-builder.
- **Pozn.:** ověřit, že `noreply@stychy.cz` je na hostingu validní odesílací adresa (jinak fallback na doménovou adresu hostingu).

### Chunk B: Kontaktní sekce na login.html + AJAX
- **Produces:** pod přihlašovacím boxem sekce `#kontakt`. **Copy (jasné):** „Záznamník je zatím soukromý – pokud máte zájem o přístup, napište mi." Formulář (jméno, e-mail, zpráva, skrytý honeypot), `fetch()` JSON POST na `api/contact.php`, feedback bez reloadu.
- **UX/a11y (Important 5–8):** `<label>` ke všem polím, `type="email"`, `maxlength`, `<textarea>` na zprávu; během odesílání **disable tlačítka** (stav odesílám→OK→chyba); po úspěchu **reset** polí; feedback přes **`textContent`** (ne innerHTML) v prvku s `aria-live="polite"`; přidat **`mailto:` fallback** odkaz pro případ selhání JS; drobná GDPR pozn.
- **Proves success:** odeslání bez reloadu; validace; feedback; vizuál ladí (tmavý+mint, sdílené CSS proměnné z login.html).
- **Touches:** `login.html` (markup, CSS, JS).
- **Constraints:** nerozbít stávající login formulář (jiná id); konzistentní styl.
- **HITL:** ne. **Executor:** BaC-builder. **Depends on:** A.

### Chunk C: Footer odkaz „Kontakt" [replanning: přímo do index-visual-refresh]
- **Produces:** diskrétní odkaz „Kontakt" ve footeru index.html → `login.html#kontakt`.
- **Touches:** `index.html` footer **na větvi `index-visual-refresh`** (ne samostatná větev) → žádný merge konflikt.
- **HITL:** ne. **Executor:** BaC-builder. **Depends on:** je součást stejné větve.

### Chunk D: Ověření
- **Produces:** lokální ověření (JSON odpovědi, validace, AJAX bez reloadu v dockeru), pak produkční test doručení e-mailu.
- **Proves success:** na produkci přijde testovací e-mail na honza@tobolik.cz.
- **HITL:** **[HITL]** produkční test doručení.
- **Executor:** BaC-builder + Honza.
- **Depends on:** A–C + deploy.

### Chunk E: Deploy [replanning: rollback + verze]
- **Produces:** vše na `index-visual-refresh` → CHANGELOG (vizuál + kontakt v jednom **v1.5.0**) → commit → PR → merge master → lftp deploy → ověření.
- **Rollback (Important 10):** pokud po deployi `contact.php` hází 500 nebo testovací e-mail **do 5 min** nedorazí na honza@tobolik.cz → `git revert` merge commitu + fix mail konfigu + nová PR.
- **Constraints:** žádné staging (jde rovnou živě).
- **HITL:** **[HITL]** merge/deploy + **[HITL]** produkční test doručení (5 min timeout).
- **Executor:** BaC-builder + Honza. **Depends on:** A–D.

## Critical path
P0 → A → B → (C) → [HITL deploy] → produkční test e-mailu.

## Definition of done
- Formulář na login.html funguje (AJAX, validace, feedback), footer odkaz vede na něj.
- Testovací e-mail dorazí na honza@tobolik.cz z produkce.
- Honza odsouhlasí; nasazeno.

## Otevřené body
- Ověřit platnou odesílací adresu na hostingu (`noreply@stychy.cz` vs doménová adresa hostingu) kvůli SPF/doručitelnosti.

## Replanning log (2026-06-22)
**Reviewed by:** Bezpečnost & PHP mail, Feasibility & rizika, Codebase alignment, Fresh perspective
**Findings:** 4 Critical, 7 Important, 5 Minor
**Změny zapracované:**
- [Critical] Vše na jedné větvi `index-visual-refresh` (vizuál+formulář+footer) → odpadá merge konflikt footeru, jeden deploy v1.5.0. Samostatná contact-form větev zrušena.
- [Critical] api/contact.php podle vzoru auth.php (getJsonInput/jsonResponse/error handlery), ne NetWalking echo.
- [Critical] Anti header-injection: pevné From `noreply@stychy.cz`, Reply-To odesílatel, odmítnout CRLF; UTF-8 Content-Type.
- [Important] Serverová validace + délky, rate-limit, mail()===false ošetření, UX stavy + aria-live + textContent, mailto fallback, jasné copy, rollback + 5min HITL na doručení.
- [Minor zaznamenáno] GDPR pozn., X-Mailer, honeypot (CAPTCHA až časem), CSRF nízké riziko.
