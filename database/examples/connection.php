<?php
declare(strict_types=1);

/** Configure as variáveis no ambiente do servidor; este arquivo não lê .env. */
function atlasConnection(): PDO
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $database = getenv('DB_NAME') ?: 'grand_line_atlas';
    $username = getenv('DB_USER');
    $password = getenv('DB_PASSWORD');
    if ($username === false || $username === '' || $password === false) {
        throw new RuntimeException('Configure DB_USER e DB_PASSWORD no ambiente do servidor.');
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/D', $database) || !ctype_digit($port)) {
        throw new RuntimeException('Configuração do banco inválida.');
    }
    return new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
        ]
    );
}
