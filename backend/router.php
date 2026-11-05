<?php
declare(strict_types=1);

/**
 * Roteador do servidor embutido do PHP. O frontend estático (dist/) continua sendo
 * servido como antes; apenas /app/* é tratado aqui.
 *
 *   php -S localhost:8000 -t dist backend/router.php
 */

use Atlas\App;
use Atlas\Auth;
use Atlas\Controller;

require_once __DIR__ . '/src/bootstrap.php';

$path = rawurldecode((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
if ($path !== App::BASE && !str_starts_with($path, App::BASE . '/')) {
    return false; // arquivo estático de dist/
}

// ------------------------------------------------- arquivos próprios (/app/assets)
if (str_starts_with($path, App::BASE . '/assets/')) {
    $name = substr($path, strlen(App::BASE . '/assets/'));
    $types = ['css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8'];
    $file = realpath(__DIR__ . '/assets/' . $name);
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($file === false || !str_starts_with($file, realpath(__DIR__ . '/assets') . DIRECTORY_SEPARATOR) || !isset($types[$ext])) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $types[$ext]);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-cache');
    readfile($file);
    exit;
}

// ------------------------------------------------------- cabeçalhos de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
    . "font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Cache-Control: private, no-store');

try {
    App::startSession();
    $method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $route = rtrim(substr($path, strlen(App::BASE)), '/');
    $route = $route === '' ? '/' : $route;

    $pdo = App::pdo();
    $app = new Controller($pdo);
    $id = '([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})';

    /** @var list<array{0:string,1:string,2:callable}> $routes */
    $routes = [
        ['GET', '#^/$#', static fn () => redirect(url('/decks'))],
        ['GET', '#^/login$#', [$app, 'loginForm']],
        ['POST', '#^/login$#', [$app, 'login']],
        ['GET', '#^/cadastro$#', [$app, 'registerForm']],
        ['POST', '#^/cadastro$#', [$app, 'register']],
        ['POST', '#^/logout$#', [$app, 'logout']],
        ['GET', '#^/decks$#', [$app, 'decksIndex']],
        ['GET', '#^/decks\.pdf$#', [$app, 'decksPdf']],
        ['GET', '#^/decks/novo$#', [$app, 'deckNew']],
        ['POST', '#^/decks$#', [$app, 'deckCreate']],
        ['GET', "#^/decks/{$id}$#", [$app, 'deckShow']],
        ['GET', "#^/decks/{$id}\.pdf$#", [$app, 'deckPdf']],
        ['GET', "#^/decks/{$id}/editar$#", [$app, 'deckEdit']],
        ['POST', "#^/decks/{$id}/editar$#", [$app, 'deckUpdate']],
        ['POST', "#^/decks/{$id}/itens$#", [$app, 'deckAddItem']],
        ['POST', "#^/decks/{$id}/itens/atualizar$#", [$app, 'deckUpdateItems']],
        ['POST', "#^/decks/{$id}/lider$#", [$app, 'deckSetLeader']],
        ['POST', "#^/decks/{$id}/excluir$#", [$app, 'deckDelete']],
    ];

    $pathMatched = false;
    foreach ($routes as [$verb, $pattern, $handler]) {
        if (preg_match($pattern, $route, $matches) !== 1) {
            continue;
        }
        $pathMatched = true;
        if ($verb !== $method) {
            continue;
        }
        if ($method === 'POST' && !App::csrfValid(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null)) {
            http_response_code(419);
            render('error', ['title' => 'Sessão expirada', 'user' => Auth::user($pdo), 'code' => 419,
                'message' => 'Sua sessão expirou ou o formulário é antigo. Volte, recarregue a página e tente novamente.']);
            exit;
        }
        $handler(...array_slice($matches, 1));
        exit;
    }

    if ($pathMatched) {
        http_response_code(405);
        header('Allow: GET, POST');
        render('error', ['title' => 'Método não permitido', 'user' => Auth::user($pdo), 'code' => 405, 'message' => 'Esta ação não aceita este tipo de requisição.']);
        exit;
    }
    $app->notFound();
} catch (Throwable $error) {
    // Detalhes só no log do servidor: nunca expor SQL, caminhos ou credenciais ao navegador.
    error_log('[atlas] ' . get_class($error) . ': ' . $error->getMessage() . ' em ' . $error->getFile() . ':' . $error->getLine());
    $unavailable = $error instanceof PDOException || ($error instanceof RuntimeException && str_contains($error->getMessage(), 'DB_'));
    http_response_code($unavailable ? 503 : 500);
    render('error', [
        'title' => 'Algo deu errado',
        'user' => null,
        'code' => $unavailable ? 503 : 500,
        'message' => $unavailable
            ? 'O banco de dados não está disponível. Confira se o MySQL está ligado e se DB_USER e DB_PASSWORD estão configurados.'
            : 'Ocorreu um erro inesperado. Tente novamente em instantes.',
    ]);
}
