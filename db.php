<?php
function getDB() {
    $url = getenv('DATABASE_URL');
    if (!$url) {
        // Fallback avec les credentials directs
        $host = getenv('DB_HOST') ?: 'dpg-d8u07anavr4c73a4p56g-a';
        $port = getenv('DB_PORT') ?: '5432';
        $dbname = getenv('DB_NAME') ?: 'sondage_zecv';
        $user = getenv('DB_USER') ?: 'sondage_zecv_user';
        $password = getenv('DB_PASSWORD') ?: '';
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    } else {
        // Parse DATABASE_URL postgresql://user:pass@host:port/dbname
        $parts = parse_url($url);
        $dsn = "pgsql:host={$parts['host']};port={$parts['port']};dbname=" . ltrim($parts['path'], '/') . ";sslmode=require";
        $user = $parts['user'];
        $password = $parts['pass'];
    }

    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]);
        exit;
    }
}

function initDB($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS responses (
            id SERIAL PRIMARY KEY,
            session_id VARCHAR(64) NOT NULL,
            question_index INTEGER NOT NULL,
            answer TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT NOW()
        );
        CREATE TABLE IF NOT EXISTS sessions (
            id VARCHAR(64) PRIMARY KEY,
            completed BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT NOW(),
            completed_at TIMESTAMP
        );
    ");
}
