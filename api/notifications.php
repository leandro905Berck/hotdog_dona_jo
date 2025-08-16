<?php
header('Content-Type: application/json');

session_start();
include '../includes/conexao.php';
include '../includes/functions.php';

$response = ['success' => false, 'notifications' => [], 'unread_count' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $type = $_GET['type'] ?? '';
    
    if ($type === 'admin' && isAdmin()) {
        // Get admin notifications
        $stmt = $pdo->prepare("
            SELECT * FROM notificacoes 
            WHERE destinatario = 'admin' 
            ORDER BY created_at DESC 
            LIMIT 20
        ");
        $stmt->execute();
        $notifications = $stmt->fetchAll();
        
        // Get unread count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM notificacoes WHERE destinatario = 'admin' AND lida = 0");
        $unread_count = $stmt->fetch()['count'];
        
        $response = [
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unread_count
        ];
        
    } elseif (isLoggedIn()) {
        // Get client notifications
        $stmt = $pdo->prepare("
            SELECT * FROM notificacoes 
            WHERE destinatario = 'cliente' AND cliente_id = ? AND lida = 0
            ORDER BY created_at DESC
        ");
        $stmt->execute([$_SESSION['cliente_id']]);
        $notifications = $stmt->fetchAll();
        
        $response = [
            'success' => true,
            'notifications' => $notifications
        ];
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'mark_read') {
        $ids = $input['ids'] ?? [];
        
        if (!empty($ids) && is_array($ids)) {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            
            // Verify ownership/permission
            $where_condition = '';
            $params = $ids;
            
            if (isAdmin()) {
                $where_condition = "destinatario = ?";
                $params[] = 'admin';
            } elseif (isLoggedIn()) {
                $where_condition = "destinatario = ? AND cliente_id = ?";
                $params[] = 'cliente';
                $params[] = $_SESSION['cliente_id'];
            }
            
            if ($where_condition) {
                $stmt = $pdo->prepare("UPDATE notificacoes SET lida = 1 WHERE id IN ($placeholders) AND $where_condition");
                $success = $stmt->execute($params);
                
                $response['success'] = $success;
            }
        }
    }
}

echo json_encode($response);
?>