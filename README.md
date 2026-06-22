# Štychy Online

Online verze klasické české karetní hry Štychy (trick-taking, varianta „Oh Hell").

🎮 **Demo:** [stychy.cz](https://stychy.cz) · 📋 [CHANGELOG](CHANGELOG.md)

> Tenhle projekt je zároveň **ukázka**, jak může 16letá studentka (Juli) postavit
> funkční webovou appku s pomocí AI. Není to komerční produkt, ale showcase procesu.

## Funkce

- 🃏 **Hra proti AI** – singleplayer s boty (3–11 hráčů, 1–2 balíčky)
- 📝 **Záznamník offline her** – zapisování sázek a štychů po kolech s přáteli
- 🏆 **Roast statistiky** – po hře dostane každý hráč vtipné ocenění (poslední vždy „roast")
- 🎙️ **Hlasové ovládání** – zadávání hlášení a výsledků českými povely
- 📱 **Mobilní optimalizace** – zápis kola se vejde na jednu obrazovku telefonu
- 👤 **Uživatelské účty** – registrace a přihlášení
- 📊 **Historie her** – ukládání výsledků do databáze

## Instalace

### Požadavky

- PHP 7.4+
- MySQL/MariaDB
- Webový server (Apache/Nginx)

### Postup

1. **Klonujte repozitář**
   ```bash
   git clone https://github.com/vas-username/stychy.git
   cd stychy
   ```

2. **Vytvořte konfiguraci databáze**
   ```bash
   cp config/database.example.php config/database.php
   ```
   Upravte `config/database.php` s vašimi přihlašovacími údaji.

3. **Importujte databázové schéma**
   ```bash
   mysql -u uzivatel -p nazev_databaze < database/schema.sql
   ```
   Nebo přes phpMyAdmin.

4. **Nahrajte na server**
   - FTP/SFTP upload na váš hosting

## Lokální vývoj (Docker)

Pro rychlé lokální prostředí (PHP + MySQL) bez instalace:

```bash
docker compose -f docker-compose.dev.yml up -d --build
# web: http://localhost:8088
```

Docker soubory jsou v `.gitignore` a nikdy se nedeployují.

## Nasazení

Produkce (**stychy.cz**) se nasazuje automaticky přes GitHub Actions
([.github/workflows/deploy.yml](.github/workflows/deploy.yml)) při **push/merge do `master`**
– SFTP upload přes `lftp`. Žádné staging prostředí; merge do `master` jde rovnou živě.

Verze je ve footeru aplikace (`vX.Y.Z`); při releasu se zapisuje do [CHANGELOG.md](CHANGELOG.md).
Detailní pokyny pro vývoj viz [CLAUDE.md](CLAUDE.md).

## Struktura projektu

```
stychy/
├── api/                    # PHP API endpointy
│   ├── auth.php           # Autentizace
│   ├── games.php          # Správa her
│   └── rounds.php         # Správa kol
├── config/                 # Konfigurace
│   ├── database.php       # DB credentials (gitignore)
│   └── database.example.php
├── database/               # SQL schémata
│   └── schema.sql
├── includes/               # PHP knihovny
│   ├── auth.php           # Auth třída
│   ├── db.php             # DB připojení
│   └── helpers.php        # Pomocné funkce
├── database/migrations/    # DB migrace
├── docs/                   # Plány, audity, handoffy
├── index.html             # Úvodní stránka
├── game.html              # Hra proti AI
├── login.html             # Přihlášení
├── register.html          # Registrace
├── offline-recorder.html  # Záznamník her (hlavní appka)
├── CHANGELOG.md           # Historie verzí
└── CLAUDE.md              # Pokyny pro vývoj (AI/dev)
```

## Pravidla hry

Štychy je karetní hra typu "trick-taking":

1. Hráči dostanou karty (začíná se na 7, pak 6, 5... 1... a zpět)
2. Otočená karta určí trumfovou barvu
3. Každý hráč nahlásí, kolik štychů (zdvihů) vezme
4. Dealer nesmí nahlásit tak, aby součet = počet karet
5. Bodování: splněný odhad = 10 + počet štychů, nesplněno = záporné body

## Autoři

Vytvořeno s ❤️ a pomocí AI – jako ukázka, co dnes zvládne junior s dobrými nástroji.

- **Juli** – studentka gymnázia, herní logika a design
- **Honza** – AI konzultant a školitel, příběh a nasazení

## Licence

MIT License
