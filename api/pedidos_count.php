<?php
header('Content-Type: application/json');

session_start();
include '../includes/conexao.php';
include '../includes/functions.php';

$response = ['count' => 0];

if (isAdmin()) {
    try {
        $stmt = $pdo->query("SELECT COUNT(DISTINCT id) as count FROM pedidos WHERE status = 'pendente'");
        $result = $stmt->fetch();
        $response['count'] = (int)$result['count'];
    } catch (PDOException $e) {
        error_log("Erro ao contar pedidos: " . $e->getMessage());
        $response['count'] = 0;
    }
}

echo json_encode($response);
?>