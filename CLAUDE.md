# CLAUDE.md – Štychy Online

Projektové instrukce pro práci s tímto repem. (Doplňuje globální `~/.claude/CLAUDE.md`.)

## Co to je

Webová verze klasické české karetní hry **Štychy** (trick-taking, varianta „Oh Hell"). Zároveň je to **ukázková / fanouškovská** stránka demonstrující, jak může 16letá studentka (Juli) stavět funkční appku s pomocí AI – cílem NENÍ prodej ani konverze, ale ukázat proces. Tonalita a messaging mají odpovídat showcase, ne produktu.

## Architektura

Žádný build step. Vanilla HTML + inline CSS/JS frontend, PHP + MySQL backend.

- `index.html` – úvodní/landing stránka (statická, pro nepřihlášené)
- `game.html` – hra proti AI (klientská logika)
- `offline-recorder.html` – **hlavní appka**: záznamník offline her (~4700 ř., inline CSS/JS). Tady se odehrává většina práce.
- `login.html` / `register.html` – účty
- `api/*.php` – REST-ish endpointy (auth, games, rounds), PDO/MySQL, JSON
- `includes/` – `db.php` (PDO singleton), `auth.php`, `helpers.php`
- `database/schema.sql` + `database/migrations/`
- `config/database.php` – **gitignored** (credentials). Vzor: `database.example.php`

### Sdílený design system
Frontendy sdílí stejné CSS proměnné v `:root` (`--bg-color #1a1c2c`, `--accent #00ffcc`, `--surface`, `--text-main`, `--text-muted`, `--danger #ff5e5e`, `--success #2ecc71`, `--warning #ffcc00`). Pozor: některé soubory dřív používaly nedefinované proměnné (`--card-bg`, `--text`, `--primary`) – v `offline-recorder.html` jsou nadefinované jako aliasy. Při práci drž konzistenci přes proměnné, ne hardcoded barvy.

## Deploy (DŮLEŽITÉ)

- Produkce **stychy.cz** se nasazuje přes GitHub Actions (`.github/workflows/deploy.yml`) **jen při push/merge do `master`** (SFTP přes `lftp -f`). **Žádné staging** – merge do master = okamžitě živě.
- **Nepoužívat `Dylan700/sftp-upload-action`** – rozbila se pod Node 24 (hlásila success, ale tiše nenahrávala). Nahrazeno `lftp` (šifrované sftp://, verbose log).
- SFTP secrets (`SFTP_SERVER/USERNAME/PASSWORD/PORT/REMOTE_PATH`) jsou v GitHub Secrets, neměnily se.
- Produkční DB **nemá sloupec `total_rounds`** (migrace neproběhla) – kód to ošetřuje, nevkládat ho.
- Workflow před uploadem maže `.git`, `.github`, `config/database.php`, `.gitignore`.

## Lokální vývoj

```bash
docker compose -f docker-compose.dev.yml up -d --build
# web: http://localhost:8088   login: test / test1234
```
Docker soubory (`docker-compose.dev.yml`, `Dockerfile.dev`, `config/database.docker.php`) jsou v `.gitignore` – nikdy se nedeployují.

## Konvence

- **Verzování:** verze je ve footeru frontendu (`vX.Y.Z` + `build-date`). Při releasu bumpni footer, zapiš do `CHANGELOG.md` a zmiň verzi v commitu.
- **Větve:** feature větev → PR → merge do `master` (spustí deploy). Nepracovat přímo na `master`. PR vždy přes HITL.
- **Jazyk:** UI i commit messages česky. Žádné dlouhé pomlčky (—), používej krátkou (–).
- **Pravidlo hry pro logiku:** rozdávající NESMÍ dohlásit hodnotu, kde by součet sázek = počet karet. Důsledek: kolo, kde splní všichni, je matematicky nemožné (proto se taková metrika nepoužívá).

## Ověřování

Bez test frameworku. Pro JS změny v jednom souboru: extrahuj `<script>` bloky a prožeň `node --check` / `vm.Script`. Pro statistiky/logiku piš ad-hoc node testy (extrakce funkcí přes `new Function`). Vizuál a mobil (cíl: iPhone 14 Pro Max 430×932) ověřuj v Docker prostředí / DevTools emulaci.
