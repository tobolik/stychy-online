<?php
/**
 * Konfigurace databáze - PŘÍKLAD
 * 
 * Zkopírujte tento soubor jako database.php a vyplňte své údaje:
 * cp database.example.php database.php
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'nazev_databaze');
define('DB_USER', 'uzivatel');
define('DB_PASS', 'heslo');
define('DB_CHARSET', 'utf8mb4');

// Bezpečnostní klíč pro session (změňte na náhodný řetězec)
define('SESSION_SECRET', 'zmenit_na_nahodny_retezec');

// Doba platnosti session (v sekundách)
define('SESSION_LIFETIME', 86400); // 24 hodin
