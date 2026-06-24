<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once 'db.php';
$pdo = getDB();
initDB($pdo);

$action = $_GET['action'] ?? '';

switch ($action) {

    // Créer ou vérifier une session
    case 'session':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $sessionId = $data['session_id'] ?? bin2hex(random_bytes(16));

            $stmt = $pdo->prepare("INSERT INTO sessions (id) VALUES (?) ON CONFLICT (id) DO NOTHING");
            $stmt->execute([$sessionId]);

            // Vérifier si déjà complété
            $stmt = $pdo->prepare("SELECT completed FROM sessions WHERE id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch();

            echo json_encode([
                'session_id' => $sessionId,
                'completed' => $session['completed'] ?? false
            ]);
        }
        break;

    // Sauvegarder une réponse
    case 'save':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $sessionId = $data['session_id'] ?? '';
            $questionIndex = (int)($data['question_index'] ?? -1);
            $answer = $data['answer'] ?? '';

            if (!$sessionId || $questionIndex < 0 || $answer === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Données manquantes']);
                break;
            }

            // Upsert: si la réponse existe déjà pour cette session+question, on met à jour
            $stmt = $pdo->prepare("
                INSERT INTO responses (session_id, question_index, answer)
                VALUES (?, ?, ?)
                ON CONFLICT DO NOTHING
            ");
            $stmt->execute([$sessionId, $questionIndex, $answer]);

            echo json_encode(['success' => true]);
        }
        break;

    // Marquer session comme complétée
    case 'complete':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $sessionId = $data['session_id'] ?? '';

            if (!$sessionId) {
                http_response_code(400);
                echo json_encode(['error' => 'session_id manquant']);
                break;
            }

            $stmt = $pdo->prepare("UPDATE sessions SET completed = TRUE, completed_at = NOW() WHERE id = ?");
            $stmt->execute([$sessionId]);

            echo json_encode(['success' => true]);
        }
        break;

    // Récupérer les réponses d'une session (reprise)
    case 'get_session':
        $sessionId = $_GET['session_id'] ?? '';
        if (!$sessionId) {
            http_response_code(400);
            echo json_encode(['error' => 'session_id manquant']);
            break;
        }

        $stmt = $pdo->prepare("SELECT question_index, answer FROM responses WHERE session_id = ? ORDER BY question_index");
        $stmt->execute([$sessionId]);
        $responses = $stmt->fetchAll();

        $stmt2 = $pdo->prepare("SELECT completed FROM sessions WHERE id = ?");
        $stmt2->execute([$sessionId]);
        $session = $stmt2->fetch();

        echo json_encode([
            'responses' => $responses,
            'completed' => $session['completed'] ?? false
        ]);
        break;

    // Stats pour l'admin
    case 'stats':
        $stats = [];

        // Total sessions et complétées
        $row = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN completed THEN 1 ELSE 0 END) as completed FROM sessions")->fetch();
        $stats['total_sessions'] = (int)$row['total'];
        $stats['completed_sessions'] = (int)$row['completed'];

        // Réponses par question
        $rows = $pdo->query("SELECT question_index, answer, COUNT(*) as count FROM responses GROUP BY question_index, answer ORDER BY question_index, count DESC")->fetchAll();
        $stats['answers'] = $rows;

        echo json_encode($stats);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Action inconnue']);
}
