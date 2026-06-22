# Changelog

Všechny podstatné změny projektu Štychy Online. Formát vychází z [Keep a Changelog](https://keepachangelog.com/cs/),
verzování dle [SemVer](https://semver.org/lang/cs/). Verze odpovídá údaji ve footeru aplikace.

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
