<?php
header('Content-Type: application/json');

session_start();
include '../includes/conexao.php';
include '../includes/functions.php';

$response = ['success' => false, 'freight' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bairro_id = (int)($_POST['bairro_id'] ?? 0);
    
    if ($bairro_id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT valor_frete FROM bairros WHERE id = ? AND ativo = 1");
            $stmt->execute([$bairro_id]);
            $bairro = $stmt->fetch();
            
            if ($bairro) {
                $response = [
                    'success' => true,
                    'freight' => (float)$bairro['valor_frete']
                ];
            } else {
                $response['message'] = 'Bairro não encontrado ou inativo';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Erro ao calcular frete';
        }
    } else {
        $response['message'] = 'ID do bairro inválido';
    }
}

echo json_encode($response);
?>