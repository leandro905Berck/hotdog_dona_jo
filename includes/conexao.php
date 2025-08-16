<?php
// includes/conexao.php

$host = 'sql308.infinityfree.com';
$dbname = 'if0_39693591_bancohotdog';
$username = 'if0_39693591';
$password = 'DsfSoberano';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Configurar fuso horário para Brasília (-03:00)
    $pdo->exec("SET time_zone = '-03:00'");
} catch (PDOException $e) {
    die("<h3>Erro de conexão com o banco de dados. Contate o administrador.</h3>");
}
?>