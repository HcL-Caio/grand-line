<?php
declare(strict_types=1);

use Atlas\App;

/** Escapa para HTML (todo valor dinâmico passa por aqui). */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL interna da aplicação: url('/decks', ['q' => 'x']) => /app/decks?q=x */
function url(string $path = '', array $query = []): string
{
    $query = array_filter($query, static fn ($v) => $v !== '' && $v !== null && $v !== []);
    return App::BASE . $path . ($query === [] ? '' : '?' . http_build_query($query));
}

function colorLabel(string $code): string
{
    return ['Red' => 'Vermelho', 'Green' => 'Verde', 'Blue' => 'Azul', 'Purple' => 'Roxo', 'Black' => 'Preto', 'Yellow' => 'Amarelo'][$code] ?? $code;
}

function colorHex(string $code): string
{
    return ['Red' => '#dc655c', 'Green' => '#70b68d', 'Blue' => '#659ecb', 'Purple' => '#ac84cf', 'Black' => '#a3a9ac', 'Yellow' => '#e7c96c'][$code] ?? '#e9bc65';
}

function typeLabel(string $code): string
{
    return ['LEADER' => 'Líder', 'CHARACTER' => 'Personagem', 'EVENT' => 'Evento', 'STAGE' => 'Cenário', 'DON' => 'DON!!'][$code] ?? $code;
}

/** Data/hora do banco (UTC) no fuso do projeto. */
function formatDate(?string $utc, bool $time = true): string
{
    if ($utc === null || $utc === '') {
        return '—';
    }
    try {
        $date = (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Sao_Paulo'));
    } catch (Exception) {
        return '—';
    }
    return $date->format($time ? 'd/m/Y H:i' : 'd/m/Y');
}

/**
 * Caminho da imagem local (relativo a dist/) ou null. Aceita assets/cards/ID.webp e as 14
 * artes de exemplo que ficam direto em assets/ (ex.: assets/OP01-001.png). Nada além disso.
 */
function cardImage(?string $localPath): ?string
{
    if ($localPath === null || preg_match('#^assets/(cards/)?[A-Za-z0-9][A-Za-z0-9._-]*\.(webp|png|jpg)$#D', $localPath) !== 1 || str_contains($localPath, '..')) {
        return null;
    }
    return '/' . $localPath;
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(App::csrfToken()) . '">';
}

/**
 * Renderiza views/$view.php dentro do layout.
 *
 * @param array<string,mixed> $vars
 */
function render(string $view, array $vars = [], string $layout = 'layout'): void
{
    $vars += ['title' => 'Grand Line Atlas', 'nav' => '', 'user' => null];
    $vars['flash'] = App::takeFlash();
    $render = static function (string $__file, array $__vars): string {
        extract($__vars, EXTR_SKIP);
        ob_start();
        require $__file;
        return (string)ob_get_clean();
    };
    $vars['content'] = $render(__DIR__ . '/../views/' . $view . '.php', $vars);
    echo $render(__DIR__ . '/../views/' . $layout . '.php', $vars);
}

function redirect(string $location): never
{
    // Somente caminhos internos da aplicação: bloqueia redirecionamento aberto e injeção de cabeçalho.
    if (
        !str_starts_with($location, App::BASE)
        || preg_match('/[\x00-\x1f\x7f\\\\]/', $location) === 1
        || str_starts_with($location, App::BASE . '//')
        || str_contains($location, '..')
    ) {
        $location = App::BASE . '/decks';
    }
    header('Location: ' . $location, true, 303);
    exit;
}
