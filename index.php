<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Database.php';

use App\Database;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

const JOKE_API_URL = 'https://v2.jokeapi.dev/joke/Programming?amount=10';
const CACHE_TTL_SECONDS = 300; // 5 perc

$errorMessage = null;
$fetchedFromApi = false;
$jokes = [];
$lastLogTime = null;

try {
    $pdo = Database::getConnection();

    $logStmt = $pdo->query(
        'SELECT date_time, TIMESTAMPDIFF(SECOND, date_time, NOW()) AS elapsed_seconds
         FROM logs
         ORDER BY id DESC
         LIMIT 1'
    );
    $lastLog = $logStmt->fetch();

    $shouldFetch = $lastLog === false || (int)$lastLog['elapsed_seconds'] > CACHE_TTL_SECONDS;
    if ($lastLog !== false) {
        $lastLogTime = $lastLog['date_time'];
    }

    if ($shouldFetch) {
        // Minden JokeAPI-kérést naplózunk (a sikerteleneket is).
        $insertLogStmt = $pdo->prepare('INSERT INTO logs (url, date_time) VALUES (:url, NOW())');
        $insertLogStmt->execute([':url' => JOKE_API_URL]);
        $lastLogTime = date('Y-m-d H:i:s');

        try {
            $client = new Client([
                'timeout' => 10,
                'connect_timeout' => 5,
            ]);

            $response = $client->get(JOKE_API_URL, [
                'headers' => ['Accept' => 'application/json'],
            ]);

            $payload = json_decode(
                $response->getBody()->getContents(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($payload) || ($payload['error'] ?? true) === true) {
                $apiMsg = is_array($payload) && isset($payload['message']) && is_string($payload['message'])
                    ? $payload['message']
                    : 'Ismeretlen JokeAPI hiba.';
                throw new RuntimeException($apiMsg);
            }

            $apiJokes = $payload['jokes'] ?? (isset($payload['type']) ? [$payload] : []);
            if (!is_array($apiJokes) || count($apiJokes) === 0) {
                throw new RuntimeException('A JokeAPI válaszában nem érkeztek viccek.');
            }

            $pdo->beginTransaction();
            $insertJokeStmt = $pdo->prepare(
                'INSERT INTO jokes (type, joke, setup, delivery) VALUES (:type, :joke, :setup, :delivery)'
            );

            foreach ($apiJokes as $item) {
                if (!is_array($item) || !isset($item['type'])) {
                    continue;
                }

                if ($item['type'] === 'single' && isset($item['joke']) && is_string($item['joke']) && trim($item['joke']) !== '') {
                    $insertJokeStmt->execute([
                        ':type' => 'single',
                        ':joke' => $item['joke'],
                        ':setup' => null,
                        ':delivery' => null,
                    ]);
                } elseif (
                    $item['type'] === 'twopart'
                    && isset($item['setup'], $item['delivery'])
                    && is_string($item['setup']) && trim($item['setup']) !== ''
                    && is_string($item['delivery']) && trim($item['delivery']) !== ''
                ) {
                    $insertJokeStmt->execute([
                        ':type' => 'twopart',
                        ':joke' => null,
                        ':setup' => $item['setup'],
                        ':delivery' => $item['delivery'],
                    ]);
                }
            }

            $pdo->commit();
            $fetchedFromApi = true;
        } catch (GuzzleException | JsonException | RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessage = 'Nem sikerült új vicceket lekérni a JokeAPI-ból (' . $e->getMessage() . '). A korábban elmentett viccek jelennek meg.';
        }
    }

    $jokesStmt = $pdo->query(
        'SELECT id, type, joke, setup, delivery, created_at
         FROM jokes
         ORDER BY id DESC
         LIMIT 10'
    );
    $jokes = $jokesStmt->fetchAll();
} catch (Throwable $e) {
    $errorMessage = 'Adatbázis- vagy rendszerhiba történt a viccek betöltése közben.';
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programozói viccek – JokeAPI</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        header { border-bottom: 1px solid #ddd; padding-bottom: 0.75rem; margin-bottom: 1.25rem; }
        .meta, .card-header { color: #666; font-size: 0.875rem; }
        .badge { border: 1px solid #ccc; padding: 0.1rem 0.45rem; border-radius: 4px; font-size: 0.75rem; }
        .alert-error { border: 1px solid #c00; background: #fee; color: #900; padding: 0.75rem; margin-bottom: 1rem; }
        .card { border: 1px solid #ddd; border-radius: 6px; padding: 1rem; margin-bottom: 0.75rem; }
        .card-header { display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
        .joke-setup { font-weight: bold; margin-bottom: 0.4rem; }
        .joke-text, .joke-setup, .joke-delivery { margin-top: 0; white-space: pre-line; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div>
                <h1>Programozói viccek</h1>
                <div class="meta">
                    <span>Legutóbbi lekérés: <?= $lastLogTime !== null ? e($lastLogTime) : 'még nem történt' ?></span>
                    <span>•</span>
                    <span>Cache időkorlát: 5 perc</span>
                </div>
            </div>
            <div>
                <?php if ($fetchedFromApi): ?>
                    <span class="badge badge-api">Frissítve a JokeAPI-ból</span>
                <?php else: ?>
                    <span class="badge badge-cache">Adatbázisból (cache)</span>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($errorMessage !== null): ?>
            <div class="alert-error" role="alert">
                <?= e($errorMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (count($jokes) === 0): ?>
            <div class="empty">
                Jelenleg nincs tárolt vicc az adatbázisban.
            </div>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($jokes as $joke): ?>
                    <article class="card">
                        <div class="card-header">
                            <span>#<?= (int)$joke['id'] ?> • <?= e((string)$joke['created_at']) ?></span>
                            <span class="badge <?= $joke['type'] === 'single' ? 'badge-single' : 'badge-twopart' ?>">
                                <?= e((string)$joke['type']) ?>
                            </span>
                        </div>
                        <?php if ($joke['type'] === 'single'): ?>
                            <p class="joke-text"><?= e((string)$joke['joke']) ?></p>
                        <?php else: ?>
                            <p class="joke-setup"><?= e((string)$joke['setup']) ?></p>
                            <p class="joke-delivery"><?= e((string)$joke['delivery']) ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
