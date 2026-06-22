# Changelog

Všechny podstatné změny projektu Štychy Online. Formát vychází z [Keep a Changelog](https://keepachangelog.com/cs/),
verzování dle [SemVer](https://semver.org/lang/cs/). Verze odpovídá údaji ve footeru aplikace.

## [Nepublikováno]

### Přidáno
- Standardní hamburger menu na úvodní stránce (`index.html`) pro mobil – ikona ☰↔✕,
  zavírání klikem na odkaz / mimo / klávesou Escape, `aria-expanded`.

### Plánováno (z review)
- Přerámování messagingu úvodní stránky na showcase „vytvořeno 16letou s AI", odkaz na zdrojový kód.
- Drobné a11y/UX: kontrast `--text-muted`, tap target hamburgeru ≥44px, `:focus-visible`, `preconnect` na Font Awesome.
- Oprava `selectPlayers` (předat `this` místo globálního `event`), meta/OG tagy.

## [1.4.3] – 2026-06-22

### Změněno
- Statistiky: ocenění **Vabank** nově ukazuje i kolik bodů ta nejvyšší sázka přinesla
  (např. „nejvyšší sázka 2 (+12 b)").

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
