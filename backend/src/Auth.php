<?php
declare(strict_types=1);

namespace Atlas;

use PDO;
use PDOException;

/** Cadastro, login e sessão. Autorização sempre parte do usuário carregado da sessão. */
final class Auth
{
    private const MAX_FAILURES = 5;
    private const WINDOW_SECONDS = 900;

    private static ?array $current = null;

    // --------------------------------------------------------------- senhas

    /** Argon2id quando existir; senão bcrypt, que ignora tudo após 72 bytes. */
    private static function algorithm(): string
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, self::algorithm());
    }

    private static function passwordProblem(string $password): ?string
    {
        $length = mb_strlen($password);
        if ($length < 8 || $length > 128) {
            return 'A senha deve ter de 8 a 128 caracteres.';
        }
        if (self::algorithm() === PASSWORD_BCRYPT && strlen($password) > 72) {
            return 'Neste servidor a senha pode ter no máximo 72 bytes (menos caracteres se houver acentos).';
        }
        return null;
    }

    // ------------------------------------------------------------- cadastro

    /**
     * @param array<string,mixed> $input name, email, password, password_confirm
     * @return array{ok:bool,errors:array<string,string>,user_id:?int}
     */
    public static function register(PDO $pdo, array $input): array
    {
        $name = trim((string)($input['name'] ?? ''));
        $email = mb_strtolower(trim((string)($input['email'] ?? '')));
        $password = (string)($input['password'] ?? '');
        $confirm = (string)($input['password_confirm'] ?? '');
        $errors = [];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['name'] = 'Informe um nome de 2 a 100 caracteres.';
        }
        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Informe um e-mail válido.';
        }
        if (($problem = self::passwordProblem($password)) !== null) {
            $errors['password'] = $problem;
        } elseif (!hash_equals($password, $confirm)) {
            $errors['password_confirm'] = 'A confirmação não confere com a senha.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'user_id' => null];
        }

        $exists = $pdo->prepare('SELECT 1 FROM users WHERE email = :email');
        $exists->execute(['email' => $email]);
        if ($exists->fetchColumn() !== false) {
            return ['ok' => false, 'errors' => ['email' => 'Este e-mail já está cadastrado.'], 'user_id' => null];
        }

        $hash = self::hash($password);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $userId = App::tx($pdo, function () use ($pdo, $name, $email, $hash): int {
                    $insert = $pdo->prepare(
                        "INSERT INTO users (public_id, username, email, display_name, password_hash, role_code, status)
                         VALUES (:public_id, :username, :email, :name, :hash, 'member', 'active')"
                    );
                    $insert->execute([
                        'public_id' => App::uuid(),
                        'username' => self::username($name),
                        'email' => $email,
                        'name' => $name,
                        'hash' => $hash,
                    ]);
                    $id = (int)$pdo->lastInsertId();
                    App::audit($pdo, $id, 'user.registered', 'user', (string)$id);
                    return $id;
                });
                return ['ok' => true, 'errors' => [], 'user_id' => $userId];
            } catch (PDOException $e) {
                if (!self::isDuplicate($e)) {
                    throw $e;
                }
                // Corrida no e-mail ou colisão do nome de usuário gerado.
                $exists->execute(['email' => $email]);
                if ($exists->fetchColumn() !== false) {
                    return ['ok' => false, 'errors' => ['email' => 'Este e-mail já está cadastrado.'], 'user_id' => null];
                }
            }
        }
        return ['ok' => false, 'errors' => ['email' => 'Não foi possível concluir o cadastro. Tente novamente.'], 'user_id' => null];
    }

    /** Nome de usuário interno: único, sem espaços; o login usa e-mail. */
    private static function username(string $name): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $slug = strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii === false ? '' : $ascii), '-'));
        return substr($slug === '' ? 'nakama' : $slug, 0, 40) . '-' . bin2hex(random_bytes(3));
    }

    private static function isDuplicate(PDOException $e): bool
    {
        $state = (string)$e->getCode();
        $driver = $e->errorInfo[1] ?? null;
        return $state === '23000' && ($driver === 1062 || $driver === 19 || $driver === null);
    }

    // ---------------------------------------------------------------- login

    /** @return array{ok:bool,error:?string} */
    public static function login(PDO $pdo, string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));
        $key = hash('sha256', $email);

        if (self::recentFailures($pdo, $key) >= self::MAX_FAILURES) {
            return ['ok' => false, 'error' => 'Muitas tentativas. Aguarde alguns minutos e tente novamente.'];
        }

        $statement = $pdo->prepare(
            'SELECT user_id, password_hash, status FROM users WHERE email = :email AND deleted_at IS NULL'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        // Sempre verifica um hash, para não revelar por tempo se o e-mail existe.
        static $dummy = null;
        $dummy ??= self::hash('senha-inexistente-' . bin2hex(random_bytes(8)));
        $valid = password_verify($password, $user ? (string)$user['password_hash'] : $dummy);

        if (!$user || !$valid || $user['status'] !== 'active') {
            App::audit($pdo, null, 'auth.login_failed', 'login', $key);
            return ['ok' => false, 'error' => 'E-mail ou senha incorretos.'];
        }

        $userId = (int)$user['user_id'];
        if (password_needs_rehash((string)$user['password_hash'], self::algorithm())) {
            $pdo->prepare('UPDATE users SET password_hash = :hash WHERE user_id = :id')
                ->execute(['hash' => self::hash($password), 'id' => $userId]);
        }
        self::start($userId);
        App::audit($pdo, $userId, 'auth.login', 'user', (string)$userId);
        return ['ok' => true, 'error' => null];
    }

    private static function recentFailures(PDO $pdo, string $key): int
    {
        $statement = $pdo->prepare(
            "SELECT COUNT(*) FROM audit_events
             WHERE action = 'auth.login_failed' AND entity_type = 'login' AND entity_key = :key AND occurred_at >= :since"
        );
        $statement->execute([
            'key' => $key,
            'since' => gmdate('Y-m-d H:i:s', time() - self::WINDOW_SECONDS) . '.000000',
        ]);
        return (int)$statement->fetchColumn();
    }

    /** Nova sessão após autenticar: evita fixação de sessão. */
    public static function start(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = $userId;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        self::$current = null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::$current = null;
    }

    /**
     * Usuário autenticado, relido do banco a cada requisição: contas suspensas ou
     * excluídas perdem o acesso imediatamente.
     *
     * @return ?array{user_id:int,public_id:string,display_name:string,email:string,role_code:string}
     */
    public static function user(PDO $pdo): ?array
    {
        if (self::$current !== null) {
            return self::$current;
        }
        $id = $_SESSION['uid'] ?? null;
        if (!is_int($id)) {
            return null;
        }
        $statement = $pdo->prepare(
            "SELECT user_id, public_id, display_name, email, role_code FROM users
             WHERE user_id = :id AND status = 'active' AND deleted_at IS NULL"
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if (!$row) {
            self::logout();
            return null;
        }
        $row['user_id'] = (int)$row['user_id'];
        return self::$current = $row;
    }

    public static function reset(): void
    {
        self::$current = null;
    }
}
