<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Database.php';

use App\Database;

header('Content-Type: application/json; charset=utf-8');

const ALLOWED_FIELDS = ['type', 'joke', 'setup', 'delivery'];
const ALLOWED_TYPES = ['single', 'twopart'];

function sendJson(int $statusCode, array $payload, array $extraHeaders = []): void
{
    http_response_code($statusCode);
    foreach ($extraHeaders as $name => $value) {
        header($name . ': ' . $value);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sendError(int $statusCode, string $message, array $extraHeaders = []): void
{
    sendJson($statusCode, ['error' => $message], $extraHeaders);
}

function formatJokeRow(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'type' => (string)$row['type'],
        'joke' => $row['joke'] !== null ? (string)$row['joke'] : null,
        'setup' => $row['setup'] !== null ? (string)$row['setup'] : null,
        'delivery' => $row['delivery'] !== null ? (string)$row['delivery'] : null,
        'created_at' => (string)$row['created_at'],
    ];
}

function requireJsonContentType(): void
{
    $rawContentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    $mediaType = strtolower(trim(explode(';', $rawContentType)[0]));

    if ($mediaType !== 'application/json') {
        sendError(415, 'A kérés Content-Type fejlécének application/json típusúnak kell lennie.');
    }
}

function parseJsonBody(): array
{
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false || trim($rawBody) === '') {
        sendError(400, 'A kérés törzse nem lehet üres.');
    }

    try {
        $decoded = json_decode($rawBody, false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        sendError(400, 'Érvénytelen JSON-formátum.');
    }

    if (!($decoded instanceof stdClass)) {
        sendError(400, 'A kérés törzsének JSON-objektumnak kell lennie.');
    }

    $data = get_object_vars($decoded);
    foreach (array_keys($data) as $key) {
        if (!in_array($key, ALLOWED_FIELDS, true)) {
            sendError(400, sprintf('Nem megengedett mező a kérésben: %s.', $key));
        }
    }

    return $data;
}

function validateNonEmptyString(mixed $value, string $fieldName): string
{
    if (!is_string($value) || trim($value) === '') {
        sendError(400, sprintf('A(z) "%s" mezőnek nem üres szövegnek kell lennie.', $fieldName));
    }
    return $value;
}

function validateCompleteJoke(array $record): array
{
    $type = $record['type'] ?? null;
    if (!is_string($type) || !in_array($type, ALLOWED_TYPES, true)) {
        sendError(400, 'A "type" mező értéke csak "single" vagy "twopart" lehet.');
    }

    if ($type === 'single') {
        $joke = validateNonEmptyString($record['joke'] ?? null, 'joke');
        return [
            'type' => 'single',
            'joke' => $joke,
            'setup' => null,
            'delivery' => null,
        ];
    }

    $setup = validateNonEmptyString($record['setup'] ?? null, 'setup');
    $delivery = validateNonEmptyString($record['delivery'] ?? null, 'delivery');

    return [
        'type' => 'twopart',
        'joke' => null,
        'setup' => $setup,
        'delivery' => $delivery,
    ];
}

function parsePositiveId(string $rawId): int
{
    if (preg_match('/^[1-9]\d*$/', $rawId) !== 1) {
        sendError(400, 'Az azonosítónak pozitív egész számnak kell lennie.');
    }
    return (int)$rawId;
}

function findJokeById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, type, joke, setup, delivery, created_at FROM jokes WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

try {
    $pdo = Database::getConnection();

    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = '';
    if (str_ends_with($scriptName, '/api/api.php')) {
        $basePath = rtrim(substr($scriptName, 0, -strlen('/api/api.php')), '/');
    }

    $routePath = $requestPath;
    if ($basePath !== '' && str_starts_with($requestPath, $basePath)) {
        $routePath = substr($requestPath, strlen($basePath));
    }
    $routePath = '/' . ltrim($routePath, '/');

    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    // /api/jokes
    if (preg_match('#^/api/jokes/?$#', $routePath) === 1) {
        if ($method === 'GET') {
            if (array_key_exists('type', $_GET)) {
                $typeFilter = $_GET['type'];
                if (!is_string($typeFilter) || !in_array($typeFilter, ALLOWED_TYPES, true)) {
                    sendError(400, 'A "type" szűrő értéke csak "single" vagy "twopart" lehet.');
                }
                $stmt = $pdo->prepare(
                    'SELECT id, type, joke, setup, delivery, created_at
                     FROM jokes
                     WHERE type = :type
                     ORDER BY id DESC'
                );
                $stmt->execute([':type' => $typeFilter]);
            } else {
                $stmt = $pdo->query(
                    'SELECT id, type, joke, setup, delivery, created_at
                     FROM jokes
                     ORDER BY id DESC'
                );
            }

            $rows = $stmt->fetchAll();
            $result = array_map('formatJokeRow', $rows);
            sendJson(200, $result);
        }

        if ($method === 'POST') {
            requireJsonContentType();
            $body = parseJsonBody();
            $validated = validateCompleteJoke($body);

            $stmt = $pdo->prepare(
                'INSERT INTO jokes (type, joke, setup, delivery, created_at)
                 VALUES (:type, :joke, :setup, :delivery, NOW())'
            );
            $stmt->execute([
                ':type' => $validated['type'],
                ':joke' => $validated['joke'],
                ':setup' => $validated['setup'],
                ':delivery' => $validated['delivery'],
            ]);

            $newId = (int)$pdo->lastInsertId();
            $created = findJokeById($pdo, $newId);
            if ($created === null) {
                sendError(500, 'Váratlan szerverhiba történt.');
            }

            $location = ($basePath !== '' ? $basePath : '') . '/api/jokes/' . $newId;
            sendJson(
                201,
                [
                    'message' => 'A vicc sikeresen létrehozva.',
                    'data' => formatJokeRow($created),
                ],
                ['Location' => $location]
            );
        }

        sendError(405, 'A HTTP-metódus nem támogatott ezen a végponton.', ['Allow' => 'GET, POST']);
    }

    // /api/jokes/{id}
    if (preg_match('#^/api/jokes/([^/]+)/?$#', $routePath, $matches) === 1) {
        if (!in_array($method, ['GET', 'PUT', 'PATCH', 'DELETE'], true)) {
            sendError(405, 'A HTTP-metódus nem támogatott ezen a végponton.', ['Allow' => 'GET, PUT, PATCH, DELETE']);
        }

        $id = parsePositiveId($matches[1]);

        if ($method === 'GET') {
            $joke = findJokeById($pdo, $id);
            if ($joke === null) {
                sendError(404, 'A megadott azonosítójú vicc nem található.');
            }
            sendJson(200, formatJokeRow($joke));
        }

        if ($method === 'PUT') {
            requireJsonContentType();
            $body = parseJsonBody();

            $existing = findJokeById($pdo, $id);
            if ($existing === null) {
                sendError(404, 'A megadott azonosítójú vicc nem található.');
            }

            $validated = validateCompleteJoke($body);

            $stmt = $pdo->prepare(
                'UPDATE jokes
                 SET type = :type, joke = :joke, setup = :setup, delivery = :delivery
                 WHERE id = :id'
            );
            $stmt->execute([
                ':type' => $validated['type'],
                ':joke' => $validated['joke'],
                ':setup' => $validated['setup'],
                ':delivery' => $validated['delivery'],
                ':id' => $id,
            ]);

            $updated = findJokeById($pdo, $id);
            sendJson(200, [
                'message' => 'A vicc sikeresen frissítve.',
                'data' => formatJokeRow($updated),
            ]);
        }

        if ($method === 'PATCH') {
            requireJsonContentType();
            $body = parseJsonBody();

            if (count($body) === 0) {
                sendError(400, 'A PATCH kérésnek legalább egy megengedett mezőt tartalmaznia kell.');
            }

            $existing = findJokeById($pdo, $id);
            if ($existing === null) {
                sendError(404, 'A megadott azonosítójú vicc nem található.');
            }

            $targetType = $body['type'] ?? $existing['type'];
            if (!is_string($targetType) || !in_array($targetType, ALLOWED_TYPES, true)) {
                sendError(400, 'A "type" mező értéke csak "single" vagy "twopart" lehet.');
            }

            if ($targetType !== $existing['type']) {
                if ($targetType === 'single' && !array_key_exists('joke', $body)) {
                    sendError(400, 'Típusváltáskor (single) a "joke" mezőt is meg kell adni.');
                }
                if ($targetType === 'twopart' && (!array_key_exists('setup', $body) || !array_key_exists('delivery', $body))) {
                    sendError(400, 'Típusváltáskor (twopart) a "setup" és a "delivery" mezőket is meg kell adni.');
                }
            }

            $merged = [
                'type' => $targetType,
                'joke' => array_key_exists('joke', $body) ? $body['joke'] : $existing['joke'],
                'setup' => array_key_exists('setup', $body) ? $body['setup'] : $existing['setup'],
                'delivery' => array_key_exists('delivery', $body) ? $body['delivery'] : $existing['delivery'],
            ];

            $validated = validateCompleteJoke($merged);

            $stmt = $pdo->prepare(
                'UPDATE jokes
                 SET type = :type, joke = :joke, setup = :setup, delivery = :delivery
                 WHERE id = :id'
            );
            $stmt->execute([
                ':type' => $validated['type'],
                ':joke' => $validated['joke'],
                ':setup' => $validated['setup'],
                ':delivery' => $validated['delivery'],
                ':id' => $id,
            ]);

            $updated = findJokeById($pdo, $id);
            sendJson(200, [
                'message' => 'A vicc sikeresen módosítva.',
                'data' => formatJokeRow($updated),
            ]);
        }

        // DELETE
        $existing = findJokeById($pdo, $id);
        if ($existing === null) {
            sendError(404, 'A megadott azonosítójú vicc nem található.');
        }

        $stmt = $pdo->prepare('DELETE FROM jokes WHERE id = :id');
        $stmt->execute([':id' => $id]);

        sendJson(200, ['message' => 'A vicc sikeresen törölve.']);
    }

    sendError(404, 'A kért végpont nem található.');
} catch (Throwable) {
    sendError(500, 'Váratlan szerverhiba történt.');
}
