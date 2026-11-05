<?php
declare(strict_types=1);

/**
 * Usado só pelos testes HTTP (via test_router.php): troca a conexão MySQL por um arquivo
 * SQLite criado por Fixture. Não é carregado em produção.
 */
require_once __DIR__ . '/../src/bootstrap.php';

$path = getenv('ATLAS_TEST_DB');
if (is_string($path) && $path !== '') {
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    Atlas\App::usePdo($pdo);
}
