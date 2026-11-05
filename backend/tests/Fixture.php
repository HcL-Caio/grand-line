<?php
declare(strict_types=1);

/**
 * Banco de teste em SQLite com o catálogo real (dist/catalog-data.json) e as views reais
 * de database/04_views.sql (com ajustes mínimos de sintaxe). Não substitui o MySQL 8.4:
 * serve para exercitar a lógica PHP e as regras das views sem depender de um servidor.
 * Para validar no MySQL de verdade, use tests/run.php --mysql.
 */
final class Fixture
{
    private const VIEWS = ['v_catalog_cards', 'v_deck_main_totals', 'v_deck_summary', 'v_deck_issues', 'v_deck_cost_curve'];

    public static function sqlite(string $path = ':memory:'): PDO
    {
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        self::schema($pdo);
        self::views($pdo);
        self::seed($pdo);
        return $pdo;
    }

    private static function schema(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE roles (role_code TEXT PRIMARY KEY, name_pt TEXT NOT NULL);
CREATE TABLE play_formats (format_code TEXT PRIMARY KEY, name_pt TEXT NOT NULL, description TEXT NOT NULL);
CREATE TABLE card_types (type_code TEXT PRIMARY KEY, name_pt TEXT NOT NULL, deck_zone TEXT NOT NULL);
CREATE TABLE colors (color_code TEXT PRIMARY KEY, name_pt TEXT NOT NULL, hex_color TEXT NOT NULL);
CREATE TABLE languages (language_code TEXT PRIMARY KEY, name_pt TEXT NOT NULL);
CREATE TABLE users (
  user_id INTEGER PRIMARY KEY AUTOINCREMENT,
  public_id TEXT NOT NULL UNIQUE,
  username TEXT NOT NULL UNIQUE,
  email TEXT NOT NULL UNIQUE COLLATE NOCASE,
  display_name TEXT NOT NULL,
  password_hash TEXT NOT NULL,
  role_code TEXT NOT NULL DEFAULT 'member' REFERENCES roles(role_code),
  status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','active','suspended')),
  email_verified_at TEXT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at TEXT NULL
);
CREATE TABLE cards (
  card_id TEXT PRIMARY KEY,
  type_code TEXT NOT NULL REFERENCES card_types(type_code),
  cost INTEGER NULL, power INTEGER NULL, counter_value INTEGER NULL, life INTEGER NULL,
  deck_copy_limit INTEGER NOT NULL DEFAULT 4 CHECK (deck_copy_limit BETWEEN 1 AND 50),
  is_preview INTEGER NOT NULL DEFAULT 0,
  first_known_release_date TEXT NULL,
  archived_at TEXT NULL
);
CREATE TABLE card_texts (
  card_id TEXT NOT NULL REFERENCES cards(card_id), language_code TEXT NOT NULL,
  name TEXT NOT NULL, effect_text TEXT NOT NULL, trigger_text TEXT NOT NULL,
  PRIMARY KEY (card_id, language_code)
);
CREATE TABLE card_colors (
  card_id TEXT NOT NULL REFERENCES cards(card_id), color_code TEXT NOT NULL REFERENCES colors(color_code),
  PRIMARY KEY (card_id, color_code)
);
CREATE TABLE printings (printing_id INTEGER PRIMARY KEY AUTOINCREMENT, card_id TEXT NOT NULL REFERENCES cards(card_id), archived_at TEXT NULL);
CREATE TABLE printing_assets (
  printing_id INTEGER NOT NULL REFERENCES printings(printing_id), asset_role TEXT NOT NULL DEFAULT 'front',
  local_path TEXT NULL, PRIMARY KEY (printing_id, asset_role)
);
CREATE TABLE decks (
  deck_id INTEGER PRIMARY KEY AUTOINCREMENT,
  public_id TEXT NOT NULL UNIQUE,
  user_id INTEGER NOT NULL REFERENCES users(user_id),
  name TEXT NOT NULL,
  format_code TEXT NOT NULL DEFAULT 'study' REFERENCES play_formats(format_code),
  leader_card_id TEXT NULL REFERENCES cards(card_id),
  leader_printing_id INTEGER NULL,
  don_card_id TEXT NULL REFERENCES cards(card_id),
  don_printing_id INTEGER NULL,
  don_quantity INTEGER NOT NULL DEFAULT 10 CHECK (don_quantity BETWEEN 0 AND 10),
  visibility TEXT NOT NULL DEFAULT 'private' CHECK (visibility IN ('private','unlisted','public')),
  status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','ready','archived')),
  description TEXT NULL,
  revision INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at TEXT NULL
);
CREATE TABLE deck_items (
  deck_id INTEGER NOT NULL REFERENCES decks(deck_id) ON DELETE CASCADE,
  card_id TEXT NOT NULL REFERENCES cards(card_id),
  preferred_printing_id INTEGER NULL,
  quantity INTEGER NOT NULL CHECK (quantity BETWEEN 1 AND 50),
  PRIMARY KEY (deck_id, card_id)
);
CREATE TABLE audit_events (
  event_id INTEGER PRIMARY KEY AUTOINCREMENT,
  actor_user_id INTEGER NULL REFERENCES users(user_id),
  action TEXT NOT NULL, entity_type TEXT NOT NULL, entity_key TEXT NOT NULL,
  request_id TEXT NULL, metadata TEXT NULL,
  occurred_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX ix_audit_login ON audit_events(action, entity_key, occurred_at);
SQL);
    }

    /** Usa as definições reais de 04_views.sql, trocando apenas o que o SQLite não conhece. */
    private static function views(PDO $pdo): void
    {
        $sql = (string)file_get_contents(__DIR__ . '/../../database/04_views.sql');
        foreach (preg_split('/;\s*\n/', $sql) ?: [] as $statement) {
            if (!preg_match('/CREATE SQL SECURITY INVOKER VIEW (\w+)/', $statement, $m) || !in_array($m[1], self::VIEWS, true)) {
                continue;
            }
            $statement = preg_replace('/^--.*$/m', '', $statement);
            $statement = str_replace(
                ['SQL SECURITY INVOKER ', 'JSON_ARRAYAGG(', 'JSON_ARRAY()', 'CAST(NULL AS CHAR(32))'],
                ['', 'json_group_array(', 'json_array()', 'NULL'],
                (string)$statement
            );
            $pdo->exec($statement);
        }
    }

    private static function seed(PDO $pdo): void
    {
        $root = __DIR__ . '/../../dist';
        $catalog = json_decode((string)file_get_contents($root . '/catalog-data.json'), true, 512, JSON_THROW_ON_ERROR);
        $manifest = (string)file_get_contents($root . '/image-manifest.js');
        $images = json_decode(substr($manifest, strpos($manifest, '{'), strrpos($manifest, '}') - strpos($manifest, '{') + 1), true, 512, JSON_THROW_ON_ERROR)['images'];

        $pdo->exec("INSERT INTO roles VALUES ('member','Membro'),('editor','Editor de conteúdo'),('admin','Administrador')");
        $pdo->exec("INSERT INTO play_formats VALUES ('study','Estudo / casual','Revisão da estrutura básica.'),('standard','Standard','Consultar regulamento vigente.')");
        $pdo->exec("INSERT INTO card_types VALUES ('LEADER','Líder','leader'),('CHARACTER','Personagem','main'),('EVENT','Evento','main'),('STAGE','Cenário','main'),('DON','DON!!','resource')");
        $pdo->exec("INSERT INTO languages VALUES ('en','Inglês'),('pt-BR','Português brasileiro')");
        $pdo->exec("INSERT INTO colors VALUES ('Red','Vermelho','#dc655c'),('Green','Verde','#70b68d'),('Blue','Azul','#659ecb'),('Purple','Roxo','#ac84cf'),('Black','Preto','#a3a9ac'),('Yellow','Amarelo','#e7c96c')");

        $number = static fn (mixed $v): ?int => is_string($v) && ctype_digit($v) ? (int)$v : (is_int($v) ? $v : null);
        $seen = [];
        $pdo->beginTransaction();
        $insertCard = $pdo->prepare('INSERT INTO cards (card_id, type_code, cost, power, counter_value, life, deck_copy_limit, is_preview) VALUES (?,?,?,?,?,?,?,?)');
        $insertText = $pdo->prepare("INSERT INTO card_texts VALUES (?, 'en', ?, ?, ?)");
        $insertColor = $pdo->prepare('INSERT OR IGNORE INTO card_colors VALUES (?, ?)');
        $insertPrint = $pdo->prepare('INSERT INTO printings (card_id) VALUES (?)');
        $insertAsset = $pdo->prepare("INSERT INTO printing_assets (printing_id, asset_role, local_path) VALUES (?, 'front', ?)");
        foreach ($catalog['cards'] as $card) {
            $id = (string)$card['id'];
            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $limit = preg_match('/any number of (this card|cards with this card name)/i', (string)$card['effect']) === 1 ? 50 : 4;
                $insertCard->execute([$id, $card['type'], $number($card['cost']), $number($card['power']), $number($card['counter']), $number($card['life']), $limit, $card['spoiler'] ? 1 : 0]);
                $insertText->execute([$id, (string)$card['name'], (string)$card['effect'], (string)$card['trigger']]);
                foreach (array_filter(explode('/', (string)$card['color'])) as $color) {
                    $insertColor->execute([$id, $color]);
                }
            }
            $insertPrint->execute([$id]);
            if (isset($images[$card['uid']])) {
                $insertAsset->execute([(int)$pdo->lastInsertId(), $images[$card['uid']]]);
            }
        }
        $pdo->commit();
    }
}
