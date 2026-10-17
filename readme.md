# Základna

Osobní přehled aut, peněz, cílů a domácnosti. Nette 3.2 + Nextras ORM, Vite + Tailwind 4 + Alpine.js.

## Instalace

```
composer install
npm install
npm run build
```

1. Vytvoř databázi (MariaDB/MySQL) a spusť postupně `db/migrations/*.sql`.
2. Přístupy k DB přepiš v `config/local.neon` (sekce `database:`), načítá se po `common.neon`.
3. `temp/` a `log/` musí být zapisovatelné. `index.php` je v rootu, `.htaccess` pouští ven jen `www/`.

## Testy

```
vendor/bin/tester tests
```

## Desktop widget (Windows)

`widget/build.cmd` zkompiluje `AnoWidget.exe` (.NET Framework 4, bez instalace). Vedle exe dej `widget.ini` podle `widget.ini.example` — heslo se po prvním spuštění zašifruje (DPAPI). Data bere z `GET /api/widget`.
