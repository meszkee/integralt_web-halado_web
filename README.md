# 5. feladat – JokeAPI, PHP és REST API

Ez a projekt programozással kapcsolatos vicceket kér le a [JokeAPI](https://v2.jokeapi.dev/) szolgáltatásból (Guzzle segítségével), MySQL-adatbázisban tárolja őket (PDO használatával), megjeleníti azokat az `index.php` oldalon, valamint egy teljes CRUD REST API-t biztosít a tárolt viccek kezeléséhez.

## Követelmények

- PHP 8.1+ (PDO MySQL kiterjesztéssel)
- Composer
- MySQL / MariaDB

## Telepítés és beállítás

### 1. Adatbázis létrehozása

Futtassa le a `database.sql` fájlt a MySQL/MariaDB szerveren (ez létrehozza a `task5_jokes` adatbázist, valamint a `jokes` és `logs` táblákat):

```bash
mysql -u root -p < database.sql
```

### 2. Környezeti változók beállítása (`.env`)

Másolja le a `.env.example` fájlt `.env` névre, majd állítsa be benne a saját adatbázis-adatait:

```bash
cp .env.example .env
```

A `.env` fájl mezői:
- `DB_HOST` (alapértelmezett: `127.0.0.1`)
- `DB_PORT` (alapértelmezett: `3306`)
- `DB_NAME` (alapértelmezett: `task5_jokes`)
- `DB_USER` (alapértelmezett: `root`)
- `DB_PASSWORD`

### 3. Függőségek telepítése

Telepítse a Composer-függőségeket (`guzzlehttp/guzzle`, `vlucas/phpdotenv`):

```bash
composer install
```

### 4. Alkalmazás indítása

Indítsa el a beépített PHP webszervert a projekt gyökérkönyvtárából a `router.php` segítségével:

```bash
php -S localhost:8000 router.php
```

Ezután a weboldal elérhető a `http://localhost:8000` címen, a REST API pedig a `http://localhost:8000/api/jokes` végponton.

*(Apache használata esetén a `.htaccess` automatikusan átirányítja az `/api/...` kéréseket az `api/api.php` fájlra.)*

## REST API végpontok

- `GET /api/jokes` – Összes vicc lekérése (`id` szerint csökkenő sorrendben). Opcionális szűrő: `?type=single` vagy `?type=twopart`.
- `GET /api/jokes/{id}` – Egy vicc lekérése azonosító alapján.
- `POST /api/jokes` – Új vicc létrehozása (`Content-Type: application/json`).
- `PUT /api/jokes/{id}` – Meglévő vicc teljes cseréje (`Content-Type: application/json`).
- `PATCH /api/jokes/{id}` – Meglévő vicc részleges módosítása (`Content-Type: application/json`).
- `DELETE /api/jokes/{id}` – Vicc törlése.

## Postman-kollekció használata

1. Importálja a `Task5_Jokes_API.postman_collection.json` fájlt a Postmanbe.
2. A kollekció **Variables** fülén állítsa be a `baseUrl` változó értékét az alkalmazás alapcímére záró perjel nélkül (pl. `http://localhost:8000`).
3. Futtassa le a teljes kollekciót a **Collection Runner** segítségével – a `POST` kérés válasza automatikusan elmenti a `jokeId` változót a következő kérésekhez.
