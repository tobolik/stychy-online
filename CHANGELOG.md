# Changelog

Všechny podstatné změny projektu Štychy Online. Formát vychází z [Keep a Changelog](https://keepachangelog.com/cs/),
verzování dle [SemVer](https://semver.org/lang/cs/). Verze odpovídá údaji ve footeru aplikace.

## [1.5.9] – 2026-06-23

### Výkon
- Font Awesome se načítá **neblokujícím způsobem** (preload → stylesheet swap + `<noscript>`
  fallback, SRI zachován) na všech stránkách – nečeká se na 102KB icon font před vykreslením.
- `api/games.php` detail hry: výsledky všech kol staženy **jedním dotazem** místo N+1
  (1 hra ≈ 3 dotazy místo ~17), seskupení v PHP. Odpověď beze změny, kolo bez výsledků
  se neztratí (defenzivní inicializace).

## [1.5.8] – 2026-06-22

### Změněno
- Statistiky hry: mřížka přehledu i ocenění přepnuta ze 3 na **2 sloupce** – karty
  (počet dělitelný 2/4) tak lícují do 2×2 a nezůstává „sirotek". Na úzkém mobilu (≤480px) 1 sloupec.

## [1.5.7] – 2026-06-22

### Bezpečnost (hardening po druhém auditu)
- **SRI** integrity + `crossorigin` na Font Awesome CDN (index/login/register/záznamník) –
  ochrana proti kompromitaci/MITM externího zdroje.
- **API jen přes POST**: `auth.php`, `games.php`, `rounds.php` odmítnou ne-POST (405) a už
  nečtou `action`/`game_id`/`status` z querystringu – CSRF defense-in-depth k `SameSite=Strict`.
- `game.html`: jména hráčů escapována (`escapeHtml`) před vložením do `innerHTML` (self-XSS hygiena).
- Ownership check v `rounds.php` na striktní porovnání `(int) !==` (místo volného `!=`).
- Kontaktní formulář: do anti-injection regexu e-mailu přidány znaky `<>`.

## [1.5.6] – 2026-06-22

### Bezpečnost
- Přidán `.htaccess` s bezpečnostními hlavičkami (X-Frame-Options, CSP frame-ancestors,
  X-Content-Type-Options, Referrer-Policy, Permissions-Policy, HSTS), zákaz výpisu adresářů
  a blokace citlivých souborů. Aplikuje se na Apache/LiteSpeed; na čistém nginxu nutno
  nastavit ekvivalent v konfiguraci serveru.
- Pozn.: CSRF je krytý `SameSite=Strict` cookie; plná CSP politika (script/style-src) by
  vyžadovala refaktor inline kódu – ponecháno jako vědomé rozhodnutí.

## [1.5.5] – 2026-06-22

### Bezpečnost (audit OWASP)
- Smazán `api/test.php` (vystavoval PHP verzi, db_host/db_name, seznam tabulek).
- API už nevrací detail chyb klientovi (`$e->getMessage()`, soubor/řádek) – generická hláška, detail jen do logu (auth/games/rounds).
- Deploy nenahrává na web `database/` (schéma + migrace), `config/database.example.php`, docs ani `.gitignore`.
- XSS: hlasový toast přes `textContent`, našeptávač jmen přes `data-*` atributy + `escapeAttr` (ošetřen apostrof/uvozovky).
- Odstraněny zbytečné CORS hlavičky (`Access-Control-Allow-Origin: *`) ze všech API (web je same-origin).
- Login: základní ochrana proti brute-force (5 pokusů → 5 min lockout) + `session_regenerate_id()` (ochrana proti session fixation). Minimální heslo 6 → 8 znaků.
- Odstraněn matoucí no-op `in_array($action, [])` v games.php (auth se vynucuje bezpodmínečně).

## [1.5.4] – 2026-06-22

### Změněno
- Kontaktní formulář: zprávy chodí i na **julie@tobolikova.cz** (vedle honza@tobolik.cz).

## [1.5.3] – 2026-06-22

### Změněno
- Kontaktní formulář: pole přejmenováno na **„Jméno a příjmení"**.
- Sjednoceno oslovení do množného čísla („ozveme se vám" i v serverové hlášce).

## [1.5.2] – 2026-06-22

### Změněno
- Kontaktní formulář přesunut z login.html na **úvodní stránku** (index.html, sekce „Napište nám"),
  oslovení změněno na množné „Napište nám". Odkaz „Kontakt" ve footeru vede na `#kontakt`.

## [1.5.1] – 2026-06-22

### Změněno
- **Sjednocené verzování:** footer ukazuje stejnou verzi na všech stránkách
  (úvodní i záznamník), místo dřívějších rozdílných čísel (1.5.0 vs 1.4.4).
- Kontaktní sekce na login.html přejmenována ze „Zájem o přístup?" na neutrální
  „Napište mi" (registrace je otevřená, zápisy jsou soukromé per uživatel –
  žádost o přístup není potřeba).

## [1.5.0] – 2026-06-22

Vizuální refresh úvodní stránky (`index.html`). Texty beze změny.

### Přidáno
- Nový vzhled hero: dvousloupcový layout + **ambientní Canvas animace karet** –
  simulace hry dle pravidel štychů (trumf v rohu, štych bere správná karta) střídaná
  se scénou „vějíř" (otočení karty, odlet/přílet, zhoupnutí), trumf na konci hry
  efektně odletí obloukem. Respektuje `prefers-reduced-motion`.
- Sticky horní lišta s **hamburger menu** pro mobil (☰↔✕, zavírání link/mimo/Escape, aria).
- **Kontaktní formulář** na login.html (sekce „Zájem o přístup") + `api/contact.php` –
  AJAX bez reloadu, e-maily na honza@tobolik.cz, honeypot anti-spam, rate-limit,
  bezpečné hlavičky (pevné From, Reply-To, ochrana proti header injection).
  Diskrétní odkaz „Kontakt" ve footeru index.html.

### Změněno
- Mobilní optimalizace úvodní stránky (hero pod sebe, animace nad obsahem).
- a11y: zesvětlen kontrast `--text-muted`, `:focus-visible` stavy, hamburger tap target ≥44px,
  `aria-hidden` na dekorativní ikony.

### Opraveno
- `selectPlayers` už nespoléhá na deprecated globální `event` (předává `this`).

## [1.4.4] – 2026-06-22

### Změněno
- Statistiky: ocenění **Vabank** ukazuje, kolik **bodů** ta nejvyšší sázka přinesla
  (např. „nejvyšší sázka 2 (+12 b)") – dává to víc smyslu než počet uhraných štychů.

## [1.4.3] – 2026-06-22

### Změněno
- Statistiky: ocenění **Vabank** nově ukazuje i počet uhraných štychů v kole s nejvyšší sázkou
  (např. „nejvyšší sázka 5 (uhrál 4)").

## [1.4.2] – 2026-06-22

### Přidáno
- Statistiky: při shodě více hráčů na vrcholu metriky se u ocenění zobrazí značka **„(děleno)"**
  a v detailu/tooltipu se vypíšou ostatní shodní hráči.

## [1.4.1] – 2026-06-22

### Změněno
- Ocenění: vráceny původní názvy **Sniper** a **Gambler**.
- Ke každému ocenění i přehledové statistice přidán **tooltip (hover) + tap detail** s vysvětlením kritéria.

### Opraveno
- **Deploy pipeline**: nahrazena nefunkční `Dylan700/sftp-upload-action` (rozbitá pod Node 24, tiše nenahrávala)
  spolehlivým `lftp -f` mirrorem. Mezikroky: pin akce v1.2.3 → lftp `-e` → lftp `-f` skript.

## [1.4.0] – 2026-06-22

Zpracování 13 uživatelských připomínek k offline záznamníku + roast statistiky.

### Přidáno
- **Roast statistiky**: 14 ocenění (8 šťouchavých + 6 pozitivních), greedy unikátní přiřazení –
  každý hráč právě 1 titul, poslední v tabulce vždy „roast". Karta na hráče.
- Zobrazení trumfu ve formuláři výsledků a ve sloupci „Karty" v tabulce kol.
- Mobilní optimalizace zápisu kola (cíl: 1 obrazovka iPhone 14 Pro Max) – kompaktní modály,
  velké tlačítka ✗/✓, velké číslo nahlášených štychů, kompaktní karty výběru trumfu.

### Změněno
- Pořadí hráčů ve „Výsledcích kola" dle aktuálního kola (rozdávající poslední).
- Tlačítko „Zadat výsledky" → „Zadat výsledky kola".
- Mrtvá metrika „Kol, kde všichni splnili" (matematicky vždy 0) nahrazena za „Nejvíc přehlášené kolo".
- Definovány dříve chybějící CSS proměnné (`--primary`, `--card-bg`, `--text`, `--bg-main`).

### Opraveno
- Průhledné pozadí našeptávače hráčů a selectu „Počet karet".
- Našeptávač nenabízí jména už zadaná v jiných pozicích.
- Změna počtu hráčů nezruší výběr počtu balíčků.
- Escape zavírá libovolný otevřený modál.

## [1.3.x] – starší

- Podpora dvou balíčků karet, max. 11 hráčů.
- Dynamický přepočet `total_score` ze záznamů kol; zpětná kompatibilita bez sloupce `total_rounds`.
- Oprava sekvence kol (4,3,2,1,1,2,3,4) a dvojitého počítání bodů.
- Hlasové ovládání (české povely), čtení výsledků, oprava výslovnosti čísel.
