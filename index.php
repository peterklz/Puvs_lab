<?php
require_once __DIR__ . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

// Create Slim app
$app = AppFactory::create();

// Add error middleware
$app->addErrorMiddleware(true, true, true);

// Database connection
function getDbConnection(): PDO 
{
    $host = $_ENV['DB_HOST'] ?? 'postgres';
    $dbname = $_ENV['DB_NAME'] ?? 'shopping_db';
    $username = $_ENV['DB_USER'] ?? 'shopping_user';
    $password = $_ENV['DB_PASS'] ?? 'shopping_pass';
    $port = $_ENV['DB_PORT'] ?? '5432';
    
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    
    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

// Database operations
class ItemStore 
{
    private static ?PDO $db = null;

    private static function getDb(): PDO 
    {
        if (self::$db === null) {
            self::$db = getDbConnection();
        }
        return self::$db;
    }

    public static function getAll(): array 
    {
        $stmt = self::getDb()->query("SELECT id, name, quantity FROM items ORDER BY id");
        return $stmt->fetchAll();
    }

    public static function getById(int $id): ?array 
    {
        $stmt = self::getDb()->prepare("SELECT id, name, quantity FROM items WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function create(string $name, int $quantity): array 
    {
        $stmt = self::getDb()->prepare("INSERT INTO items (name, quantity) VALUES (?, ?) RETURNING id, name, quantity");
        $stmt->execute([$name, $quantity]);
        return $stmt->fetch();
    }

    public static function findByName(string $name): ?array 
    {
        $stmt = self::getDb()->prepare("SELECT id, name, quantity FROM items WHERE LOWER(name) = LOWER(?)");
        $stmt->execute([$name]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function update(int $id, string $name, int $quantity): ?array 
    {
        $stmt = self::getDb()->prepare("UPDATE items SET name = ?, quantity = ? WHERE id = ? RETURNING id, name, quantity");
        $stmt->execute([$name, $quantity, $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function delete(int $id): bool 
    {
        $stmt = self::getDb()->prepare("DELETE FROM items WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function increaseQuantity(int $id, int $additionalQuantity): ?array 
    {
        $stmt = self::getDb()->prepare("UPDATE items SET quantity = quantity + ? WHERE id = ? RETURNING id, name, quantity");
        $stmt->execute([$additionalQuantity, $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}

// Helper functions
function validateItemInput(array $data): array 
{
    $errors = [];
    
    if (!isset($data['name']) || empty(trim($data['name']))) {
        $errors[] = 'Name is required';
    }
    
    if (!isset($data['quantity']) || !is_numeric($data['quantity']) || $data['quantity'] < 1) {
        $errors[] = 'Quantity must be a positive integer';
    }
    
    return $errors;
}

function jsonResponse(Response $response, $data, int $statusCode = 200): Response 
{
    $response->getBody()->write(json_encode($data));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($statusCode);
}

function errorResponse(Response $response, string $message, int $statusCode = 400): Response 
{
    return jsonResponse($response, ['error' => $message], $statusCode);
}

// Routes

// GET /items - Get all shopping items
$app->get('/items', function (Request $request, Response $response) {
    try {
        $items = ItemStore::getAll();
        return jsonResponse($response, $items);
    } catch (Exception $e) {
        return errorResponse($response, 'Server error', 500);
    }
});

// POST /items - Create or update a shopping item
$app->post('/items', function (Request $request, Response $response) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return errorResponse($response, 'Invalid JSON', 400);
        }
        
        $errors = validateItemInput($data);
        if (!empty($errors)) {
            return errorResponse($response, implode(', ', $errors), 400);
        }
        
        $name = trim($data['name']);
        $quantity = (int)$data['quantity'];
        
        // Check if item with same name already exists (case-insensitive)
        $existingItem = ItemStore::findByName($name);
        
        if ($existingItem) {
            // Update existing item quantity
            $updatedItem = ItemStore::increaseQuantity($existingItem['id'], $quantity);
            return jsonResponse($response, $updatedItem, 200);
        } else {
            // Create new item
            $newItem = ItemStore::create($name, $quantity);
            return jsonResponse($response, $newItem, 201);
        }
    } catch (Exception $e) {
        return errorResponse($response, 'Server error', 500);
    }
});

// GET /items/{itemId} - Get item by ID
$app->get('/items/{itemId}', function (Request $request, Response $response, array $args) {
    try {
        $itemId = (int)$args['itemId'];
        
        if ($itemId <= 0) {
            return errorResponse($response, 'Invalid item ID', 400);
        }
        
        $item = ItemStore::getById($itemId);
        
        if (!$item) {
            return errorResponse($response, 'Item not found', 404);
        }
        
        return jsonResponse($response, $item);
    } catch (Exception $e) {
        return errorResponse($response, 'Server error', 500);
    }
});

// PUT /items/{itemId} - Update an item
$app->put('/items/{itemId}', function (Request $request, Response $response, array $args) {
    try {
        $itemId = (int)$args['itemId'];
        
        if ($itemId <= 0) {
            return errorResponse($response, 'Invalid item ID', 400);
        }
        
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return errorResponse($response, 'Invalid JSON', 400);
        }
        
        $errors = validateItemInput($data);
        if (!empty($errors)) {
            return errorResponse($response, implode(', ', $errors), 400);
        }
        
        $name = trim($data['name']);
        $quantity = (int)$data['quantity'];
        
        $updatedItem = ItemStore::update($itemId, $name, $quantity);
        
        if (!$updatedItem) {
            return errorResponse($response, 'Item not found', 404);
        }
        
        return jsonResponse($response, $updatedItem);
    } catch (Exception $e) {
        return errorResponse($response, 'Server error', 500);
    }
});

// DELETE /items/{itemId} - Delete an item
$app->delete('/items/{itemId}', function (Request $request, Response $response, array $args) {
    try {
        $itemId = (int)$args['itemId'];
        
        if ($itemId <= 0) {
            return errorResponse($response, 'Invalid item ID', 400);
        }
        
        $deleted = ItemStore::delete($itemId);
        
        if (!$deleted) {
            return errorResponse($response, 'Item not found', 404);
        }
        
        return $response->withStatus(204);
    } catch (Exception $e) {
        return errorResponse($response, 'Server error', 500);
    }
});

$app->run();