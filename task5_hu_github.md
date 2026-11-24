# 5. feladat – JokeAPI, PHP és REST API

Készítsen PHP-alkalmazást, amely programozással kapcsolatos vicceket kér le a JokeAPI szolgáltatásból, MySQL-adatbázisban tárolja őket, és megjeleníti őket egy weboldalon. Készítsen saját REST API-t is az adatbázisban tárolt viccek lekérdezésére, létrehozására, módosítására és törlésére, és tesztelje Postmanben.

## 1. Composer, Guzzle és adatbázis

- A függőségek kezeléséhez használja a Composert. Telepítse a Guzzle csomagot a `composer require guzzlehttp/guzzle` paranccsal, és töltse be a `vendor/autoload.php` fájlt.
- Telepítse a `vlucas/phpdotenv` csomagot a `composer require vlucas/phpdotenv` paranccsal. A MySQL-kapcsolat beállításait (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` és `DB_PASSWORD`) a `.env` fájlból olvassa be. Az `index.php` és a REST API ugyanazokat az adatbázis-beállításokat használja.
- Az adatbázis eléréséhez használjon PDO-t. Az adatbeszúráshoz és a felhasználói paramétereket használó lekérdezésekhez alkalmazzon előkészített SQL-utasításokat.
- Hozza létre a következő táblákat:
  - `jokes` (`id`, `type`, `joke`, `setup`, `delivery`, `created_at`)
  - `logs` (`id`, `url`, `date_time`)
- A `jokes` tábla `id` mezője helyi, automatikusan növekvő elsődleges kulcs. A `single` típushoz a `joke` mezőt, a `twopart` típushoz a `setup` és a `delivery` mezőket töltse ki. A nem használt mezők értéke legyen `NULL`.

## 2. Viccek lekérése és megjelenítése

1. Az `index.php` megnyitásakor ellenőrizze a `logs` táblában az utolsó kérés időpontját. Ha a tábla üres, vagy több mint 5 perc telt el, küldjön kérést a Guzzle segítségével a [JokeAPI – Programming](https://v2.jokeapi.dev/joke/Programming?amount=10) végpontjára.
2. Naplózza a JokeAPI-nak küldött minden kérés URL-jét és időpontját, a sikertelen kérésekét is. Dekódolja a JSON-választ, és sikeres válasz esetén mentse a kapott 10 viccet a `jokes` táblába. Ha a kérés sikertelen, jelenítsen meg hibaüzenetet, és tartsa meg a korábban elmentett vicceket.
3. Jelenítse meg az adatbázis legfeljebb 10 legutóbbi bejegyzését, a helyi `id` szerint csökkenő sorrendben. Ha az utolsó kérés óta legfeljebb 5 perc telt el, csak az adatbázisban tárolt adatokat használja.
4. Teszteléskor az időköz lehet 1 perc. A beadott változatban állítsa vissza 5 percre.

[A JokeAPI dokumentációja](https://v2.jokeapi.dev/)

## 3. Saját REST API

Valósítsa meg az alábbi végpontokat. Minden művelet PDO-n keresztül használja a MySQL-adatbázist. A JokeAPI meghívása az `index.php` megnyitásakor történik, az előző szakaszban leírt szabályok szerint.

| Kérés | Művelet | Sikeres válasz |
| --- | --- | --- |
| `GET /api/jokes` | Az összes tárolt vicc lekérése a legújabbtól a legrégebbiig. | `200 OK` és JSON-tömb. Ha nincs bejegyzés, a válasz `[]`. |
| `GET /api/jokes/{id}` | Egy vicc lekérése helyi azonosító alapján. Példa: `GET /api/jokes/1`. | `200 OK` és a vicc JSON-objektumként. |
| `POST /api/jokes` | Új vicc létrehozása. Az `id` és a `created_at` értékét a szerver adja meg. | `201 Created`, az új vicc URL-jét tartalmazó `Location` fejléc és JSON-válasz `message` és `data` mezővel. |
| `PUT /api/jokes/{id}` | Egy meglévő vicc teljes tartalmának cseréje. Küldje el a `type` mezőt és a típushoz tartozó összes kötelező mezőt. | `200 OK` és JSON-válasz `message` és `data` mezővel. |
| `PATCH /api/jokes/{id}` | A megadott mezők módosítása. A módosítások alkalmazása után és a mentés előtt ellenőrizze a teljes rekordot. | `200 OK` és JSON-válasz `message` és `data` mezővel. |
| `DELETE /api/jokes/{id}` | Egy meglévő vicc törlése. | `200 OK` és JSON-válasz `message` mezővel. |

A POST, PUT és PATCH válaszában a `data` mező a mentett vicc objektumát, a `message` mező a sikeres művelet leírását tartalmazza.

### JSON-kérések és adatellenőrzés

- A POST, PUT és PATCH kérések törzse JSON-objektum, a kérés fejléce `Content-Type: application/json`. A megengedett mezők a `type`, a `joke`, a `setup` és a `delivery`. Módosításkor őrizze meg az eredeti `id` és `created_at` értéket.
- A megengedett típusok a `single` és a `twopart`. A `single` típusnál a `joke` mezőben, a `twopart` típusnál a `setup` és a `delivery` mezőkben kell nem üres szöveget megadni. A kiválasztott típushoz nem használt mezők értéke legyen `NULL` az adatbázisban, PUT vagy PATCH művelettel végzett típusváltáskor is.
- A PATCH kérés legalább egy megengedett mezőt tartalmazzon. Típusváltáskor az új típushoz tartozó összes kötelező szöveges mezőt is küldje el.

### HTTP-státuszkódok és üzenetek

A REST API minden válaszának fejléce `Content-Type: application/json`. A státuszkódot a HTTP-válaszban állítsa be. A hiba leírását JSON-objektumban adja meg, például `{"error":"A megadott azonosítójú vicc nem található."}`.

| Státusz | Mikor adja vissza? |
| --- | --- |
| `400 Bad Request` | Az azonosító nem pozitív egész szám, a JSON hibás, vagy az adatok nem felelnek meg az ellenőrzési szabályoknak. |
| `404 Not Found` | A megadott pozitív azonosítóhoz nincs rekord, vagy a végpont nem létezik. |
| `405 Method Not Allowed` | A végpont létezik, de a HTTP-metódus nem támogatott. Az `Allow` fejlécben adja meg a támogatott metódusokat. |
| `415 Unsupported Media Type` | A POST, PUT vagy PATCH kérés tartalomtípusa nem `application/json`. |
| `500 Internal Server Error` | Váratlan szerverhiba. Adjon vissza általános, JSON-formátumú hibaüzenetet. |

**Opcionális** – Egészítse ki a listázó végpontot a `type` szűrővel. Példa: `GET /api/jokes?type=single`. Az elfogadott értékek a `single` és a `twopart`. Más érték esetén adjon vissza `400 Bad Request` státuszt és `error` mezőt tartalmazó JSON-választ.

## 4. Postman-kollekció

Hozzon létre **Task 5 – Jokes API** néven egy kollekciót. A `baseUrl` változó az alkalmazás alapcímét tartalmazza záró perjel nélkül. A POST válasz `data.id` értékét automatikusan mentse a `jokeId` változóba, majd használja a `{{jokeId}}` változót a módosító és törlő kérésekben. Minden kéréshez adjon hozzá automatikus ellenőrzést a státuszkódra és a JSON-válaszra.

| Sorrend | Kérés | Ellenőrzés |
| --- | --- | --- |
| 1 | `GET /api/jokes` | `200` és JSON-tömb. |
| 2 | `POST /api/jokes` | `201`, üzenet és a létrehozott rekord azonosítóval. |
| 3 | `GET /api/jokes/{{jokeId}}` | `200` és a létrehozott rekord. |
| 4 | `PUT /api/jokes/{{jokeId}}` | `200`, üzenet és a lecserélt tartalom. |
| 5 | `PATCH /api/jokes/{{jokeId}}` | `200`, üzenet és a megadott mező módosítása. A szövegmódosítási tesztben a többi mező értéke megmarad. |
| 6 | `DELETE /api/jokes/{{jokeId}}` | `200` és törlési üzenet. |
| 7 | `GET /api/jokes/{{jokeId}}` | `404` és `error` mező. |
| 8 | `GET /api/jokes/0` és az ellenőrzési szabályoknak nem megfelelő adatokat tartalmazó POST | Mindkét kérésre `400` és `error` mező. |

Ha megvalósította a szűrést, tesztelje érvényes és érvénytelen `type` értékkel is. Futtassa le a teljes kollekciót Postmanben, majd exportálja `Task5_Jokes_API.postman_collection.json` néven.

## 5. Beadás a GitHub és a Moodle 2 használatával

Töltse fel a projektet saját GitHub-repozitóriumába, majd a **Moodle 2** felületén a megfelelő feladathoz adja be a repozitórium URL-jét. Privát repozitórium esetén a beadás előtt adjon hozzáférést az oktatónak.

A repozitórium tartalmazza a következőket.

- A PHP-forráskódot és a táblákat létrehozó SQL-szkriptet.
- A `composer.json` és a `composer.lock` fájlt.
- Az adatbázis beállításaihoz mintaértékeket tartalmazó `.env.example` fájlt.
- A `Task5_Jokes_API.postman_collection.json` néven exportált Postman-kollekciót.
- Egy `README.md` fájlt, amely leírja az adatbázis beállítását, az `.env.example` fájl másolását helyi `.env` fájlba, a függőségek telepítését a `composer install` paranccsal, az alkalmazás indítását és a Postman `baseUrl` változójának beállítását.

Vegye fel a `.env` fájlt és a `vendor/` könyvtárat a `.gitignore` fájlba. A tényleges hozzáférési adatokat a helyi `.env` fájlban tárolja.
