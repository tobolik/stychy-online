# Štychy Online

Online verze klasické české karetní hry Štychy.

🎮 **Demo:** [stychy.cz](https://stychy.cz)

## Funkce

- 🃏 **Hra proti AI** - Singleplayer verze s chytrými boty (3-7 hráčů)
- 📝 **Záznamník offline her** - Zapisujte výsledky her s přáteli
- 👤 **Uživatelské účty** - Registrace a přihlášení
- 📊 **Historie her** - Ukládání výsledků do databáze

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
├── index.html             # Hlavní stránka
├── game.html              # Hra proti AI
├── login.html             # Přihlášení
├── register.html          # Registrace
└── offline-recorder.html  # Záznamník her
```

## Pravidla hry

Štychy je karetní hra typu "trick-taking":

1. Hráči dostanou karty (začíná se na 7, pak 6, 5... 1... a zpět)
2. Otočená karta určí trumfovou barvu
3. Každý hráč nahlásí, kolik štychů (zdvihů) vezme
4. Dealer nesmí nahlásit tak, aby součet = počet karet
5. Bodování: splněný odhad = 10 + počet štychů, nesplněno = záporné body

## Autoři

Vytvořeno s ❤️ pomocí AI

- **Honza** - AI konzultant a školitel
- **Juli** - Studentka gymnázia

## Licence

MIT License
