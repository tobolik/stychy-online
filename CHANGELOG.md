# Changelog

Všechny podstatné změny projektu Štychy Online. Formát vychází z [Keep a Changelog](https://keepachangelog.com/cs/),
verzování dle [SemVer](https://semver.org/lang/cs/). Verze odpovídá údaji ve footeru aplikace.

## [1.12.0] – 2026-07-08

### Přidáno
- **Herní modály Hlášení a Výsledky na celou obrazovku** s výrazně větším písmem –
  jména, tlačítka i badge se dají číst od stolu. Obsah je ve středovém sloupci
  (~1180 px), pozadí vyplní celý viewport. Jemná animace při otevření (respektuje
  `prefers-reduced-motion`). Mobil zůstává kompaktní (písmo škáluje přes `clamp`).
- **Přepínač světlý/tmavý režim v záznamníku je nově plovoucí** (kolečko vpravo dole,
  jako na ostatních stránkách), takže je vždy po ruce.
- Titulek výběru trumfu ukazuje **celkový počet kol**: „Kolo 1/15 – Výběr trumfu".

### Změněno
- Na konci hry se automaticky zaostří tlačítko **Ukončit hru** (když není jiná primární
  akce), aby ho šlo potvrdit Enterem.

## [1.11.5] – 2026-07-07

### Změněno
- Při prohlížení průběžných výsledků je **hlavní akční tlačítko** (Nové kolo / Zadat
  hlášení / Zadat výsledky) vždy zaostřené, takže **Enter rovnou spustí další krok**.
  Odstraněny starší duplicitní Enter-handlery (nahrazeny fokusem), aby se akce nespouštěla dvakrát.

## [1.11.4] – 2026-07-07

### Opraveno
- Hlas: po posledním hlášení (rozdávající) se nově řekne i **přetlak/podtlak** („Přetlak
  o dva" / „Podtlak o jedna"). Dřív se toto oznámení hned přepsalo hláškou čísla, takže
  nebylo slyšet – teď je součástí finálního oznámení.

## [1.11.3] – 2026-07-07

### Opraveno
- Hlas: čísla nahlášených štychů se čtou v **základním tvaru** (dva, tři…), ne jako
  řadovky. TTS totiž „2." (číslice s tečkou) četlo jako „druhý" – teď se čísla vyslovují
  slovem.

## [1.11.2] – 2026-07-07

### Změněno
- Výběr trumfu: **Enter na kartě barvy nově rovnou pokračuje k hlášení** (vybere barvu
  a otevře hlášení jedním Enterem, dřív bylo potřeba Enter dvakrát).

## [1.11.1] – 2026-07-07

### Změněno
- Průběžné pořadí: skóre posunuto o kousek doleva (sloupec jmen 180 → 150 px), aby se
  vešlo i „b." i když panel prosvítá zpoza modalu trumfu.
- Hlasové oznámení zjednodušeno: **neříká „rozdává"** (jen jméno hráče) a **nahlášené
  štychy čte jako pouhá čísla** (bez slova „štych/y/ů").

## [1.11.0] – 2026-07-06

### Přidáno – ovládání hlášení a výsledků
- **Šipky nahoru/dolů** posouvají zelený rámeček mezi hráči (v hlášení i ve výsledcích) –
  lze se tak vracet a opravovat.
- Po uložení hlášení se **rovnou otevře zadávání výsledků** kola (není potřeba druhý krok).
- **Hlasové oznámení po zadání hlášení** – nahlas se řekne právě zadané hlášení a vyzve
  se další hráč (u rozdávajícího včetně zakázané hodnoty).

### Změněno
- Modal výběru trumfu je **užší**: menší karty (~10 %), větší jméno rozdávajícího a počet
  karet – a hlavně **průběžné pořadí (body) prosvítá zpoza modalu** (v doku vlevo/vpravo),
  takže při volbě trumfu vidíš aktuální stav. Sloupec jmen v pořadí zúžen, aby body byly víc vlevo.

## [1.10.4] – 2026-07-06

### Změněno
- V doku (statistiky vlevo/vpravo) je nově **horní okraj tabulky zarovnaný s panelem
  průběžného pořadí** (dřív začínala tabulka o ~20 px níž kvůli svému `margin-top`).

## [1.10.3] – 2026-07-06

### Změněno
- Tabulka výsledků má nově **zaoblené rohy** (a jemný rámeček) – sjednoceno se
  zbytkem panelů (dřív měla ostré rohy).

## [1.10.2] – 2026-07-06

### Změněno
- Úchyt pro přetažení doku statistik je nově **viditelný ovladač „✥ Přesunout"**
  (dřív jen decentní tečky ⠿, snadno k přehlédnutí). Nadpis panelu se v případě
  potřeby zkrátí, aby se ovladače vždy vešly.

## [1.10.1] – 2026-07-06

### Přidáno – dok statistik: vlevo + přetahování
- Panel "Průběžné pořadí" lze nově doknout i **vlevo** (vedle tabulky) – k dosavadním
  volbám dole a vpravo. Přepínač má tři šipky (←/↓/→).
- **Drag-to-dock:** panel lze chytit za úchyt (⠿) a **přetáhnout myší** dolů/vlevo/vpravo –
  během tažení se ukáže náhled cílové zóny, po puštění se panel přichytí (a volba se uloží).

## [1.10.0] – 2026-07-06

### Přidáno – dokovatelný panel průběžného pořadí
- Panel "Průběžné pořadí" lze nově **doknout dolů** (výchozí, pod tabulku) nebo
  **doprava** (vedle tabulky výsledků). Přepínač je v hlavičce panelu.
- V režimu vpravo web **využije celou šířku obrazovky** (žádné prázdné okraje) a panel
  **drží pozici** (sticky) i když tabulka roste přibývajícími koly.
- Volba se ukládá do `localStorage` a platí i po znovunačtení. Na úzkém displeji
  (< 900 px) se panel vždy poskládá pod tabulku.

## [1.9.4] – 2026-07-06

### Změněno
- Zákaz hodnoty rozdávajícího: místo široké lišty nově **malý plný toast přímo pod
  zakázaným tlačítkem** – "Není možné zvolit X štych(y/ů)" (se skloňováním). Toast
  sleduje tlačítko při scrollu a po pár sekundách sám zmizí.

## [1.9.3] – 2026-07-06

### Změněno (hlášení – chování hlášek)
- Tlačítko „Uložit hlášení" je nově **hned pod posledním řádkem** (žádné rezervované
  ani roztahující místo). Hlášky (zakázaná hodnota / přetlak-podtlak) se zobrazují jako
  **plovoucí overlay nad tlačítkem** (z-index) – tlačítkem už nehýbou.
- Hlášky po pár sekundách **samy zmizí** (plovoucí toast, auto-hide).
- Zakázaná hodnota rozdávajícího se ukazuje **průběžně** (kdykoliv je známá), ne jen
  když je rozdávající na řadě. Červené ohraničení zakázaného tlačítka zůstává trvale.
- Hláška zkrácena na **„{Jméno} – nemůžeš X štych/y/ů"** (se správným skloňováním).

## [1.9.2] – 2026-07-06

### Změněno
- Ve výsledcích kola popisek pod jménem hráče „Hlásil" → **„Hlášeno"** (gendrově
  neutrální – u ženských jmen působil mužský tvar rušivě).

## [1.9.1] – 2026-07-06

### Opraveno
- **Hlášení – rozdávající se ořezával dole.** Když přišel na řadu rozdávající (poslední
  hráč) a objevila se žlutá hláška „Rozdávající nesmí nahlásit…", hláška zmenšila seznam
  a poslední řádek se uřízl. Scroll se nově spouští až po přepočtu rozložení
  (`requestAnimationFrame`) + pojistka, že aktuální řádek je vždy celý vidět.

## [1.9.0] – 2026-07-06

### Přidáno – flexibilní správa kol
- **Doplnit / opravit libovolné kolo:** tlačítko tužka je nově u KAŽDÉHO kola v tabulce.
  Dokončené kolo → oprava výsledků; kolo se zadaným hlášením → zadání výsledků;
  prázdné kolo → doplnění hlášení. Řeší zaseknutá prázdná kola, která dřív nešla vyplnit.
- **Smazat kolo přímo z tabulky:** u prázdného kola (kdekoliv) a u posledního kola.
  - Prázdné kolo lze smazat i „uprostřed" (úklid zaseknutých kol) – soft-delete, bez dopadu na skóre.
  - Poslední kolo lze smazat i se zadanými výsledky (undo) – skóre se automaticky přepočítá.
  - Dřívější kolo se zadanými výsledky smazat nelze (rozbila by se posloupnost) – hláška to vysvětlí.

### Změněno – hlasové oznámení
- `speak()` vybírá **nejkvalitnější dostupný český hlas** (přednost Google/neural před
  defaultním hlasem OS). Bez nákladů – využívá hlasy prohlížeče/systému.

### Ověřeno
- Server (`rounds.php` delete): `php -l` čistý; E2E v dev dockeru (MariaDB 11.8):
  undo posledního skórovaného kola + přepočet total_score, zákaz mazání dřívějšího
  skórovaného kola (HTTP 400), smazání prostředního prázdného kola bez poškození dat
  (total_score i čtení hry v pořádku). Klient: tlačítka u kol + oprava správného kola.

## [1.8.2] – 2026-07-06

### Změněno (modal hlášení – další ladění z reálné hry)
- Zrušená rezervovaná (prázdná) oblast nad tlačítkem „Uložit hlášení". Hlášky
  (zakázaná hodnota / souhrn) se zobrazují **dynamicky** nad tlačítkem; když nejsou,
  tlačítko je hned pod řádky.
- Otevření hlášení nově **sroluje na prvního hlásiče** (hráč po rozdávajícím) – dřív
  mohl být oříznutý nahoře, když scroll zůstal z předchozího kola.
- Při zadávání se scroll posune tak, aby byl vidět **aktuální hráč + 2 řádky pod ním**
  (dřív jen aktuální na spodním okraji).

## [1.8.1] – 2026-07-06

### Změněno (modaly hlášení a výsledky – po zpětné vazbě z reálné hry)
- **Kompaktnější řádky** – vejde se jich na obrazovku 6 místo 3: zmenšeno písmo
  jmen (2rem → 1.4rem), stažen `line-height` (jméno + „Hlásil" už neroztahují řádek),
  menší tlačítka a padding, odstraněny nápovědné texty („Zadejte hlášení klávesami…",
  „Klávesnice: 0=Nesplnil…") a nadbytečný podtitul.
- **Zakázaná hodnota rozdávajícího** je nově zvýrazněná **červeným ohraničením**
  (dřív jen ztlumená) – jasně vidět, které číslo nesmí rozdávající zvolit.
- **Desktop:** tlačítka hlášení 0–N využívají celou šířku řádku (dřív vpravo prázdné
  místo), sloupec jmen zúžen; jméno rozdávajícího s „(rozdává)" se vejde bez oříznutí.

## [1.8.0] – 2026-07-06

### Přidáno
- **Světlý režim (dark/light mode) na celém webu** (index, záznamník, hra, přihlášení,
  registrace). Výchozí režim se řídí systémem (`prefers-color-scheme`); přepínač (☀/🌙)
  umožňuje ruční volbu, která se ukládá do `localStorage` a platí napříč stránkami.
  Volba se aplikuje ještě před vykreslením (žádné probliknutí – FOUC).

### Technické řešení
- Token systém: tmavá paleta v `:root`, světlé hodnoty definované jednou (`--l-*`)
  a remapované přes `@media (prefers-color-scheme: light)` + `:root[data-theme="light"]`
  (ruční volba přebíjí systém v obou směrech).
- Nový token `--accent-ink` = accent jako **text** (na světlé tmavší teal `#00786a`,
  kvůli čitelnosti/WCAG), zatímco `--accent` zůstává živý pro tlačítka/rámečky.
- Ošetřena tvrdě zadaná pozadí (tmavé pásy, bílé texty na světlých plochách, herní stůl
  a boxy hráčů); karty ve hře si drží tmavý rámeček, aby byly na světlém stole vidět.
- Přepínač: auth stránky dole vpravo (plovoucí), záznamník v hlavičce, hra dole vpravo.

## [1.7.0] – 2026-07-06

### Opraveno
- **Kritické: duplicitní kola při dvojkliku.** „Pokračovat k hlášení" šlo odeslat vícekrát
  a vytvořilo 2-3 kola. Nově: klient blokuje dvojklik (in-flight flag + disable tlačítka)
  a server je idempotentní – pokud poslední kolo ještě nemá hlášení, vrátí ho místo vytvoření
  nového (+ fallback při souběhu). Ověřeno E2E: 3 souběžná volání = 1 kolo.
- Našeptávač jmen hráčů: klávesová navigace (šipky nahoru/dolů, Enter vybere, Esc zavře).

### Přidáno
- Mazání kola je opět možné, ale **jen poslední a prázdné** kolo (soft-delete) – úklid
  duplicit z dvojkliku. Kolo s hlášením smazat nelze (pro opravu slouží úprava výsledků).
- Audio hláška rozdávajícímu, kolik štychů nesmí zvolit („Nahlášeno X z Y, rozdávající
  nemůže zvolit Z štychů").

### Změněno
- Layout maximalizace (mobil i desktop): kompaktní 2řádkový blok rozdávajícího s velkým
  jménem a velkým číslem karet; herní modály širší na desktopu s větším písmem; sticky
  tlačítko Uložit (vždy viditelné i u 11 hráčů); průběžné pořadí ~1.6× větší písmo se
  skóre hned vedle jména. Ověřeno Playwright screenshoty na 430px i desktopu.
- Doladění po živém testu: rozdávající zvýrazněn (jméno v barevném rámečku, velké číslo
  karet, mini-karty jako decentní dekorace); tlačítka volby hodnoty karty +50 %; body
  v průběžném pořadí zarovnané do sloupce (ikony pořadí fixní šířky); jména ve výsledcích
  kola 2× větší.

### Ovládání
- Výběr trumfu z klávesnice: šipky přepínají mezi 4 kartami barvy, Enter kartu vybere
  a přesune fokus na „Pokračovat k hlášení" (fokus viditelný jen při ovládání klávesnicí).

## [1.6.1] – 2026-06-24

### Bezpečnost / data (soft-delete, Fáze 2)
- Zrušeno `ON DELETE CASCADE` na všech 6 cizích klíčích (migrace `003b`) → databáze nově
  **odmítne** jakýkoli tvrdý `DELETE` na řádek s navázanými záznamy (defense-in-depth proti
  omylnému/ručnímu smazání). Po Fázi 1 už žádný kód natvrdo nemaže; tohle je pojistka.
- Ověřeno na MariaDB 11.8 s kopií prod dat: 6 cascade → 0 (6× RESTRICT), hard DELETE na
  games/users/rounds/game_players odmítnut (errno 1451), 0 řádků smazáno, reverzní SQL čisté.
- Doplněn read-filtr `valid_to IS NULL` na zbývající vstupní body (finish + rounds.php
  save_bids/save_results/get/update_results) – engine bere konzistentně jen nesmazané.

## [1.6.0] – 2026-06-23

### Bezpečnost / data (soft-delete, Fáze 1)
- Mazání hry nově **soft-deletuje celý strom** (hra + hráči + kola + výsledky) v jedné
  transakci s audit stopou (`valid_to` + `valid_to_user_id`). Nic se fyzicky nemaže.
- Opraveny dva živé bugy: smazaná hra už nešla editovat přes rounds.php (přidán filtr
  `valid_to IS NULL` do `verifyGameOwnership`); podstrom smazané hry se dříve nestampoval.
- **Mazání kola zakázáno** (princip „nikdy hard-delete"; kola se v praxi jen upravují přes
  Upravit výsledky). Odebráno tlačítko i endpoint hard-delete kola (+ přečíslování).
- Read dotazy filtrují soft-smazané podzáznamy (gated flagem `SOFT_DELETE_SUBTREE`).
- Migrace `003a` (aditivní sloupce + backfill už smazaných her). Ověřeno na MariaDB 11.8
  s kopií produkčních dat. Pozn.: zrušení `ON DELETE CASCADE` přijde ve Fázi 2 (samostatně).

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
