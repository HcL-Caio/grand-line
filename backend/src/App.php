<?php
declare(strict_types=1);

namespace Atlas;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Violação de regra de negócio dentro de uma transação: desfaz tudo e devolve as mensagens.
 * $reason: invalid | not_found | conflict.
 */
final class Abort extends RuntimeException
{
    /** @param list<string> $errors */
    public function __construct(public readonly array $errors, public readonly string $reason = 'invalid')
    {
        parent::__construct(implode(' ', $errors));
    }
}

/** Configuração, conexão, sessão, CSRF, mensagens rápidas e auditoria. */
final class App
{
    public const BASE = '/app';
    private const IDLE_SECONDS = 7200;

    private static ?PDO $pdo = null;
    private static int $savepoints = 0;

    /** Permite injetar a conexão (testes automatizados). */
    public static function usePdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function pdo(): PDO
    {
        return self::$pdo ??= self::connect();
    }

    /** Mesma configuração de database/examples/connection.php: variáveis de ambiente, sem .env. */
    private static function connect(): PDO
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

    /** Executa $fn em transação; dentro de outra transação usa SAVEPOINT. */
    public static function tx(PDO $pdo, callable $fn): mixed
    {
        if ($pdo->inTransaction()) {
            $name = 'atlas_sp_' . (++self::$savepoints);
            $pdo->exec("SAVEPOINT {$name}");
            try {
                $result = $fn();
                $pdo->exec("RELEASE SAVEPOINT {$name}");
                return $result;
            } catch (Throwable $e) {
                $pdo->exec("ROLLBACK TO SAVEPOINT {$name}");
                throw $e;
            }
        }
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Sufixo de bloqueio de linha; só o MySQL/InnoDB precisa dele. */
    public static function forUpdate(PDO $pdo): string
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }

    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s') . '.000000';
    }

    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    // ---------------------------------------------------------------- sessão

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_name('atlas_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        $seen = $_SESSION['seen'] ?? null;
        if (is_int($seen) && time() - $seen > self::IDLE_SECONDS) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['seen'] = time();
    }

    public static function csrfToken(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function csrfValid(?string $sent): bool
    {
        $known = $_SESSION['csrf'] ?? null;
        return is_string($known) && is_string($sent) && hash_equals($known, $sent);
    }

    public static function flash(string $kind, string $message): void
    {
        $_SESSION['flash'][] = ['kind' => $kind, 'message' => $message];
    }

    /** @return list<array{kind:string,message:string}> */
    public static function takeFlash(): array
    {
        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $messages;
    }

    // -------------------------------------------------------------- auditoria

    /** Nunca registrar senhas, tokens ou cookies em $metadata. */
    public static function audit(PDO $pdo, ?int $actor, string $action, string $entityType, string $entityKey, array $metadata = []): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO audit_events (actor_user_id, action, entity_type, entity_key, request_id, metadata, occurred_at)
             VALUES (:actor, :action, :type, :key, :request, :metadata, :at)'
        );
        $statement->execute([
            'actor' => $actor,
            'action' => $action,
            'type' => $entityType,
            'key' => $entityKey,
            'request' => self::uuid(),
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'at' => self::now(),
        ]);
    }
}
