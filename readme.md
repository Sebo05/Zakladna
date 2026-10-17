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

## Cron (upozornění + banka)

Jednou denně spouštěj `php bin/cron.php` — pošle e-mailová upozornění (STK/pojištění/známka 30, 7, 1 den předem, zítřejší události, opakované platby) a stáhne pohyby z napojených bank.

Na serveru doplň do `config/local.neon`:

```neon
parameters:
	appUrl: https://tvoje-domena.cz

mail:
	smtp: true
	host: smtp.example.com
	username: ...
	password: ...
	secure: ssl
```

Lokálně (Laragon) maily chytá Mailpit na http://localhost:8025.

`config/app.key` (šifrování bankovních tokenů) se vytvoří sám — zálohuj ho spolu s databází, bez něj je potřeba banky připojit znovu.

## Struktura

```
app/
  Bootstrap.php
  Core/RouterFactory.php
  Model/<Entita>/          entity, mapper, repository + služby (*Service.php se registrují samy)
  Security/                UserAuthenticator, AdminAuthenticator
  Presentation/            namespace = cesta (App\Presentation\<Modul>\<Presenter>)
    @head / @logo / @flashes.latte   sdílené kousky všech modulů
    Accessory/             Latte filtry
    Frontend/  App/  Admin/  Api/  Error/
      @layout.latte, BasePresenter.php
      <Presenter>/<Presenter>Presenter.php + <view>.latte
bin/cron.php               záloha DB, upozornění, banka
db/migrations/             SQL migrace (pouštět ručně po sobě)
www/Frontend/{css,js}      zdrojáky pro Vite → build do www/assets/
widget/                    desktop widget pro Windows
```
