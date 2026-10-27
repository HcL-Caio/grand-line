-- GERADO por tools/build-schema.mjs. MySQL 8.4 LTS / InnoDB / UTC.
-- Executar uma vez em schema vazio; sem DROP ou desativação de FKs.
SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;
SET time_zone = '+00:00';

-- Migrações estruturais; executar cada versão uma vez
CREATE TABLE schema_migrations (
  version VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  description VARCHAR(255) NOT NULL,
  applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Migrações estruturais; executar cada versão uma vez';

-- Provedores e atribuição; origem comunitária não equivale a oficial
CREATE TABLE data_sources (
  source_id SMALLINT UNSIGNED PRIMARY KEY,
  code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  base_url VARCHAR(2048) NOT NULL,
  is_official BOOLEAN NOT NULL DEFAULT FALSE,
  attribution TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Provedores e atribuição; origem comunitária não equivale a oficial';

-- Fotografia, hash e resultado de cada importação
CREATE TABLE import_runs (
  import_run_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_id SMALLINT UNSIGNED NOT NULL,
  snapshot_key VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  source_fetched_at DATETIME(6) NULL,
  imported_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  statistics JSON NULL,
  error_details JSON NULL,
  UNIQUE KEY uq_import_snapshot (source_id, snapshot_key),
  UNIQUE KEY uq_import_source (import_run_id, source_id),
  CONSTRAINT fk_import_source FOREIGN KEY (source_id) REFERENCES data_sources(source_id),
  CONSTRAINT ck_import_status CHECK (status IN ('pending','running','completed','failed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Fotografia, hash e resultado de cada importação';

-- Idiomas do texto e da impressão são independentes
CREATE TABLE languages (
  language_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  name_pt VARCHAR(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Idiomas do texto e da impressão são independentes';

-- Categoria da carta e zona de construção
CREATE TABLE card_types (
  type_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  name_pt VARCHAR(40) NOT NULL,
  deck_zone VARCHAR(16) NOT NULL,
  CONSTRAINT ck_type_zone CHECK (deck_zone IN ('leader','main','resource'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Categoria da carta e zona de construção';

-- Cores normalizadas; cartas multicoloridas usam relação N:N
CREATE TABLE colors (
  color_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  name_pt VARCHAR(40) NOT NULL,
  hex_color CHAR(7) CHARACTER SET ascii NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Cores normalizadas; cartas multicoloridas usam relação N:N';

-- Atributos do jogo
CREATE TABLE attributes (
  attribute_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  name_pt VARCHAR(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Atributos do jogo';

-- Raridades por impressão
CREATE TABLE rarities (
  rarity_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  name_pt VARCHAR(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Raridades por impressão';

-- Identidade pelo código público; NULL indica valor ausente, zero é válido
CREATE TABLE cards (
  card_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  type_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  cost SMALLINT UNSIGNED NULL,
  power INT UNSIGNED NULL,
  counter_value SMALLINT UNSIGNED NULL,
  life TINYINT UNSIGNED NULL,
  deck_copy_limit TINYINT UNSIGNED NOT NULL DEFAULT 4,
  is_preview BOOLEAN NOT NULL DEFAULT FALSE,
  first_known_release_date DATE NULL,
  canonical_source_id SMALLINT UNSIGNED NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  archived_at DATETIME(6) NULL,
  KEY ix_card_type_cost (type_code, cost, card_id),
  KEY ix_card_power (power, card_id),
  KEY ix_card_counter (counter_value, card_id),
  KEY ix_card_preview (is_preview, card_id),
  CONSTRAINT fk_card_type FOREIGN KEY (type_code) REFERENCES card_types(type_code),
  CONSTRAINT fk_card_source FOREIGN KEY (canonical_source_id) REFERENCES data_sources(source_id),
  CONSTRAINT ck_card_limit CHECK (deck_copy_limit BETWEEN 1 AND 50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Identidade pelo código público; NULL indica valor ausente, zero é válido';

-- Leitura canônica por idioma; tradução editorial deve ser identificada
CREATE TABLE card_texts (
  card_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  language_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name VARCHAR(255) NOT NULL,
  effect_text TEXT NOT NULL,
  trigger_text TEXT NOT NULL,
  source_id SMALLINT UNSIGNED NOT NULL,
  translation_status VARCHAR(24) NOT NULL DEFAULT 'source',
  PRIMARY KEY (card_id, language_code),
  KEY ix_ct_name (language_code, name, card_id),
  FULLTEXT KEY ft_card_text (name, effect_text, trigger_text),
  CONSTRAINT fk_ct_card FOREIGN KEY (card_id) REFERENCES cards(card_id),
  CONSTRAINT fk_ct_language FOREIGN KEY (language_code) REFERENCES languages(language_code),
  CONSTRAINT fk_ct_source FOREIGN KEY (source_id) REFERENCES data_sources(source_id),
  CONSTRAINT ck_ct_status CHECK (translation_status IN ('source','draft','reviewed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Leitura canônica por idioma; tradução editorial deve ser identificada';

-- Cores canônicas
CREATE TABLE card_colors (
  card_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  color_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY (card_id, color_code),
  KEY ix_card_colors_reverse (color_code, card_id),
  CONSTRAINT fk_card_colors_a FOREIGN KEY (card_id) REFERENCES cards(card_id),
  CONSTRAINT fk_card_colors_b FOREIGN KEY (color_code) REFERENCES colors(color_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Cores canônicas';

-- Múltiplos atributos por carta
CREATE TABLE card_attributes (
  card_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  attribute_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY (card_id, attribute_code),
  KEY ix_card_attributes_reverse (attribute_code, card_id),
  CONSTRAINT fk_card_attributes_a FOREIGN KEY (card_id) REFERENCES cards(card_id),
  CONSTRAINT fk_card_attributes_b FOREIGN KEY (attribute_code) REFERENCES attributes(attribute_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Múltiplos atributos por carta';

-- Características impressas; tradução permanece NULL até revisão
CREATE TABLE traits (
  trait_id SMALLINT UNSIGNED PRIMARY KEY,
  source_name VARCHAR(150) COLLATE utf8mb4_0900_as_cs NOT NULL UNIQUE,
  name_pt VARCHAR(150) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Características impressas; tradução permanece NULL até revisão';

-- Características da leitura canônica
CREATE TABLE card_traits (
  card_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  trait_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (card_id, trait_id),
  KEY ix_card_traits_reverse (trait_id, card_id),
  CONSTRAINT fk_card_traits_a FOREIGN KEY (card_id) REFERENCES cards(card_id),
  CONSTRAINT fk_card_traits_b FOREIGN KEY (trait_id) REFERENCES traits(trait_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Características da leitura canônica';

-- Produtos físicos e agrupamentos externos separados; catalog_key preserva o front
CREATE TABLE catalog_groups (
  group_id INT UNSIGNED PRIMARY KEY,
  catalog_key VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  group_kind VARCHAR(24) NOT NULL,
  product_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
  source_id SMALLINT UNSIGNED NOT NULL,
  source_url VARCHAR(2048) NULL,
  release_date DATE NULL,
  CONSTRAINT fk_group_source FOREIGN KEY (source_id) REFERENCES data_sources(source_id),
  CONSTRAINT ck_group_kind CHECK (group_kind IN ('product','catalog_group'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Produtos físicos e agrupamentos externos separados; catalog_key preserva o front';

-- Cada arte/impressão mantém UID, valores observados e registro original completo
CREATE TABLE printings (
  printing_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  legacy_uid VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  card_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  language_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  source_language_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  rarity_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  block_code VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NULL,
  printed_cost SMALLINT UNSIGNED NULL,
  printed_power INT UNSIGNED NULL,
  printed_counter SMALLINT UNSIGNED NULL,
  printed_life TINYINT UNSIGNED NULL,
  source_id SMALLINT UNSIGNED NOT NULL,
  import_run_id BIGINT UNSIGNED NOT NULL,
  source_url VARCHAR(2048) NOT NULL,
  release_date DATE NULL,
  is_preview BOOLEAN NOT NULL DEFAULT FALSE,
  image_status VARCHAR(32) NOT NULL,
  raw_payload JSON NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  archived_at DATETIME(6) NULL,
  UNIQUE KEY uq_print_card (printing_id, card_id),
  KEY ix_print_card_language (card_id, language_code, printing_id),
  KEY ix_print_rarity_block (rarity_code, block_code, card_id),
  KEY ix_print_preview (is_preview, image_status, card_id),
  KEY ix_print_cost (printed_cost, card_id),
  KEY ix_print_power (printed_power, card_id),
  CONSTRAINT fk_print_card FOREIGN KEY (card_id) REFERENCES cards(card_id),
  CONSTRAINT fk_print_language FOREIGN KEY (language_code) REFERENCES languages(language_code),
  CONSTRAINT fk_print_rarity FOREIGN KEY (rarity_code) REFERENCES rarities(rarity_code),
  CONSTRAINT fk_print_source FOREIGN KEY (source_id) REFERENCES data_sources(source_id),
  CONSTRAINT fk_print_run FOREIGN KEY (import_run_id, source_id) REFERENCES import_runs(import_run_id, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Cada arte/impressão mantém UID, valores observados e registro original completo';

-- Textos por impressão preservam diferenças de redação
CREATE TABLE printing_texts (
  printing_id BIGINT UNSIGNED NOT NULL,
  language_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name VARCHAR(255) NOT NULL,
  effect_text TEXT NOT NULL,
  trigger_text TEXT NOT NULL,
  PRIMARY KEY (printing_id, language_code),
  CONSTRAINT fk_pt_print FOREIGN KEY (printing_id) REFERENCES printings(printing_id),
  CONSTRAINT fk_pt_language FOREIGN KEY (language_code) REFERENCES languages(language_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Textos por impressão preservam diferenças de redação';

-- Uma impressão pode estar em mais de um produto
CREATE TABLE printing_groups (
  printing_id BIGINT UNSIGNED NOT NULL,
  group_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (printing_id, group_id),
  KEY ix_printing_groups_reverse (group_id, printing_id),
  CONSTRAINT fk_printing_groups_a FOREIGN KEY (printing_id) REFERENCES printings(printing_id),
  CONSTRAINT fk_printing_groups_b FOREIGN KEY (group_id) REFERENCES catalog_groups(group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Uma impressão pode estar em mais de um produto';

-- Características por impressão, sem apagar divergências da fonte
CREATE TABLE printing_traits (
  printing_id BIGINT UNSIGNED NOT NULL,
  trait_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (printing_id, trait_id),
  KEY ix_printing_traits_reverse (trait_id, printing_id),
  CONSTRAINT fk_printing_traits_a FOREIGN KEY (printing_id) REFERENCES printings(printing_id),
  CONSTRAINT fk_printing_traits_b FOREIGN KEY (trait_id) REFERENCES traits(trait_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Características por impressão, sem apagar divergências da fonte';

-- Arquivos fora do banco; caminhos relativos a dist e URLs de origem
CREATE TABLE printing_assets (
  printing_id BIGINT UNSIGNED NOT NULL,
  asset_role VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'front',
  remote_url VARCHAR(2048) NULL,
  local_path VARCHAR(512) NULL,
  mime_type VARCHAR(64) NOT NULL,
  file_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  byte_size INT UNSIGNED NULL,
  PRIMARY KEY (printing_id, asset_role),
  CONSTRAINT fk_pa_print FOREIGN KEY (printing_id) REFERENCES printings(printing_id),
  CONSTRAINT ck_pa_location CHECK (remote_url IS NOT NULL OR local_path IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Arquivos fora do banco; caminhos relativos a dist e URLs de origem';

-- Blocos observados por origem; não certificam legalidade
CREATE TABLE card_blocks (
  card_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  block_code VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  source_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (card_id, block_code, source_id),
  CONSTRAINT fk_cb_card FOREIGN KEY (card_id) REFERENCES cards(card_id),
  CONSTRAINT fk_cb_source FOREIGN KEY (source_id) REFERENCES data_sources(source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Blocos observados por origem; não certificam legalidade';

-- Grupos de descoberta; papéis narrativos são editoriais
CREATE TABLE discovery_tags (
  tag_code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  name_pt VARCHAR(100) NOT NULL,
  classification_kind VARCHAR(16) NOT NULL,
  description TEXT NOT NULL,
  CONSTRAINT ck_tag_kind CHECK (classification_kind IN ('printed','editorial'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Grupos de descoberta; papéis narrativos são editoriais';

-- Mesmas regras de descoberta do front, revisáveis
CREATE TABLE card_discovery_tags (
  card_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tag_code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  assignment_method VARCHAR(32) NOT NULL DEFAULT 'frontend_rule_v1',
  PRIMARY KEY (card_id, tag_code),
  KEY ix_cdt_tag (tag_code, card_id),
  CONSTRAINT fk_cdt_card FOREIGN KEY (card_id) REFERENCES cards(card_id),
  CONSTRAINT fk_cdt_tag FOREIGN KEY (tag_code) REFERENCES discovery_tags(tag_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Mesmas regras de descoberta do front, revisáveis';

-- Vocabulário de palavras-chave e janelas de ativação
CREATE TABLE keywords (
  keyword_code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  name_en VARCHAR(80) NOT NULL,
  name_pt VARCHAR(100) NOT NULL,
  category VARCHAR(16) NOT NULL,
  CONSTRAINT ck_keyword_category CHECK (category IN ('keyword','timing'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Vocabulário de palavras-chave e janelas de ativação';

-- Menção textual, não prova de habilidade permanente ou incondicional
CREATE TABLE printing_keyword_mentions (
  printing_id BIGINT UNSIGNED NOT NULL,
  keyword_code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY (printing_id, keyword_code),
  KEY ix_printing_keyword_mentions_reverse (keyword_code, printing_id),
  CONSTRAINT fk_printing_keyword_mentions_a FOREIGN KEY (printing_id) REFERENCES printings(printing_id),
  CONSTRAINT fk_printing_keyword_mentions_b FOREIGN KEY (keyword_code) REFERENCES keywords(keyword_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Menção textual, não prova de habilidade permanente ou incondicional';

-- Entidades narrativas; nomes compostos de cartas não criam personagens fictícios
CREATE TABLE characters (
  character_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  canonical_name VARCHAR(150) NOT NULL,
  biography_pt TEXT NULL,
  verification_status VARCHAR(16) NOT NULL DEFAULT 'pending',
  CONSTRAINT ck_character_status CHECK (verification_status IN ('pending','verified'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Entidades narrativas; nomes compostos de cartas não criam personagens fictícios';

-- Apelidos e grafias alternativas para busca
CREATE TABLE character_aliases (
  character_id INT UNSIGNED NOT NULL,
  language_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  alias VARCHAR(150) NOT NULL,
  PRIMARY KEY (character_id, language_code, alias),
  KEY ix_character_alias (alias, character_id),
  CONSTRAINT fk_alias_character FOREIGN KEY (character_id) REFERENCES characters(character_id),
  CONSTRAINT fk_alias_language FOREIGN KEY (language_code) REFERENCES languages(language_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Apelidos e grafias alternativas para busca';

-- Relação N:N; carga inicial liga dez personagens por igualdade de nome normalizado
CREATE TABLE card_characters (
  card_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  character_id INT UNSIGNED NOT NULL,
  match_method VARCHAR(32) NOT NULL,
  PRIMARY KEY (card_id, character_id),
  KEY ix_card_character (character_id, card_id),
  CONSTRAINT fk_cchar_card FOREIGN KEY (card_id) REFERENCES cards(card_id),
  CONSTRAINT fk_cchar_character FOREIGN KEY (character_id) REFERENCES characters(character_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Relação N:N; carga inicial liga dez personagens por igualdade de nome normalizado';

-- IDs externos por provedor; não usar IDs numéricos internos na API pública
CREATE TABLE external_card_ids (
  source_id SMALLINT UNSIGNED NOT NULL,
  external_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  card_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY (source_id, external_id),
  KEY ix_eci_card (card_id, source_id),
  CONSTRAINT fk_eci_source FOREIGN KEY (source_id) REFERENCES data_sources(source_id),
  CONSTRAINT fk_eci_card FOREIGN KEY (card_id) REFERENCES cards(card_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='IDs externos por provedor; não usar IDs numéricos internos na API pública';

-- Reservado a IDs de impressão externos confirmados, sem dedução por filename
CREATE TABLE external_printing_ids (
  source_id SMALLINT UNSIGNED NOT NULL,
  external_id VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  printing_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (source_id, external_id),
  KEY ix_epi_print (printing_id, source_id),
  CONSTRAINT fk_epi_source FOREIGN KEY (source_id) REFERENCES data_sources(source_id),
  CONSTRAINT fk_epi_print FOREIGN KEY (printing_id) REFERENCES printings(printing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Reservado a IDs de impressão externos confirmados, sem dedução por filename';

-- Perfis da aplicação; autorização deve ocorrer no servidor
CREATE TABLE roles (
  role_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  name_pt VARCHAR(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Perfis da aplicação; autorização deve ocorrer no servidor';

-- Sem usuários de exemplo; hash de senha gerado pelo PHP, nunca texto puro
CREATE TABLE users (
  user_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(254) NOT NULL UNIQUE,
  display_name VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  role_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'member',
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  email_verified_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  deleted_at DATETIME(6) NULL,
  CONSTRAINT fk_user_role FOREIGN KEY (role_code) REFERENCES roles(role_code),
  CONSTRAINT ck_user_status CHECK (status IN ('pending','active','suspended'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Sem usuários de exemplo; hash de senha gerado pelo PHP, nunca texto puro';

-- Somente SHA-256 de tokens aleatórios; nunca o token original
CREATE TABLE user_sessions (
  token_hash BINARY(32) PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  expires_at DATETIME(6) NOT NULL,
  revoked_at DATETIME(6) NULL,
  KEY ix_user_sessions_user (user_id, expires_at),
  KEY ix_user_sessions_expiry (expires_at),
  CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT ck_user_sessions_expiry CHECK (expires_at > created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Somente SHA-256 de tokens aleatórios; nunca o token original';

-- Somente SHA-256 de tokens aleatórios; nunca o token original
CREATE TABLE password_reset_tokens (
  token_hash BINARY(32) PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  expires_at DATETIME(6) NOT NULL,
  used_at DATETIME(6) NULL,
  KEY ix_password_reset_tokens_user (user_id, expires_at),
  KEY ix_password_reset_tokens_expiry (expires_at),
  CONSTRAINT fk_password_reset_tokens_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT ck_password_reset_tokens_expiry CHECK (expires_at > created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Somente SHA-256 de tokens aleatórios; nunca o token original';

-- Formatos sem regras competitivas inventadas ou eternizadas
CREATE TABLE play_formats (
  format_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  name_pt VARCHAR(80) NOT NULL,
  description TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Formatos sem regras competitivas inventadas ou eternizadas';

-- Rascunhos incompletos permitidos; publicação ready requer validação transacional
CREATE TABLE decks (
  deck_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  format_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'study',
  leader_card_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
  leader_printing_id BIGINT UNSIGNED NULL,
  don_card_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
  don_printing_id BIGINT UNSIGNED NULL,
  don_quantity TINYINT UNSIGNED NOT NULL DEFAULT 10,
  visibility VARCHAR(16) NOT NULL DEFAULT 'private',
  status VARCHAR(16) NOT NULL DEFAULT 'draft',
  description TEXT NULL,
  revision INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  deleted_at DATETIME(6) NULL,
  KEY ix_deck_user (user_id, deleted_at, updated_at),
  CONSTRAINT fk_deck_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  CONSTRAINT fk_deck_format FOREIGN KEY (format_code) REFERENCES play_formats(format_code),
  CONSTRAINT fk_deck_leader FOREIGN KEY (leader_card_id) REFERENCES cards(card_id),
  CONSTRAINT fk_deck_leader_art FOREIGN KEY (leader_printing_id, leader_card_id) REFERENCES printings(printing_id, card_id),
  CONSTRAINT fk_deck_don FOREIGN KEY (don_card_id) REFERENCES cards(card_id),
  CONSTRAINT fk_deck_don_art FOREIGN KEY (don_printing_id, don_card_id) REFERENCES printings(printing_id, card_id),
  CONSTRAINT ck_deck_leader_art CHECK (leader_printing_id IS NULL OR leader_card_id IS NOT NULL),
  CONSTRAINT ck_deck_don_art CHECK (don_printing_id IS NULL OR don_card_id IS NOT NULL),
  CONSTRAINT ck_deck_don_count CHECK (don_quantity BETWEEN 0 AND 10),
  CONSTRAINT ck_deck_visibility CHECK (visibility IN ('private','unlisted','public')),
  CONSTRAINT ck_deck_status CHECK (status IN ('draft','ready','archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Rascunhos incompletos permitidos; publicação ready requer validação transacional';

-- Uma linha por ID; artes não contornam limite de cópias
CREATE TABLE deck_items (
  deck_id BIGINT UNSIGNED NOT NULL,
  card_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  preferred_printing_id BIGINT UNSIGNED NULL,
  quantity TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (deck_id, card_id),
  KEY ix_di_card (card_id, deck_id),
  CONSTRAINT fk_di_deck FOREIGN KEY (deck_id) REFERENCES decks(deck_id) ON DELETE CASCADE,
  CONSTRAINT fk_di_card FOREIGN KEY (card_id) REFERENCES cards(card_id),
  CONSTRAINT fk_di_art FOREIGN KEY (preferred_printing_id, card_id) REFERENCES printings(printing_id, card_id),
  CONSTRAINT ck_di_quantity CHECK (quantity BETWEEN 1 AND 50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Uma linha por ID; artes não contornam limite de cópias';

-- Favoritos por ID, abrangendo suas artes
CREATE TABLE favorites (
  user_id BIGINT UNSIGNED NOT NULL,
  card_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (user_id, card_id),
  CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_fav_card FOREIGN KEY (card_id) REFERENCES cards(card_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Favoritos por ID, abrangendo suas artes';

-- Condição física; unknown não promete qualidade
CREATE TABLE card_conditions (
  condition_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  name_pt VARCHAR(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Condição física; unknown não promete qualidade';

-- Posse por ID ou impressão; escopo gerado evita duplicatas com impressão NULL
CREATE TABLE collection_entries (
  collection_entry_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  card_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  printing_id BIGINT UNSIGNED NULL,
  printing_scope BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(printing_id,0)) STORED,
  language_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'und',
  condition_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'unknown',
  quantity INT UNSIGNED NOT NULL,
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_collection_scope (user_id, card_id, printing_scope, language_code, condition_code),
  CONSTRAINT fk_ce_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_ce_card FOREIGN KEY (card_id) REFERENCES cards(card_id),
  CONSTRAINT fk_ce_art FOREIGN KEY (printing_id, card_id) REFERENCES printings(printing_id, card_id),
  CONSTRAINT fk_ce_language FOREIGN KEY (language_code) REFERENCES languages(language_code),
  CONSTRAINT fk_ce_condition FOREIGN KEY (condition_code) REFERENCES card_conditions(condition_code),
  CONSTRAINT ck_ce_quantity CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Posse por ID ou impressão; escopo gerado evita duplicatas com impressão NULL';

-- Lista de desejos com quantidade alvo e arte opcional
CREATE TABLE wishlists (
  user_id BIGINT UNSIGNED NOT NULL,
  card_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  preferred_printing_id BIGINT UNSIGNED NULL,
  target_quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  priority TINYINT UNSIGNED NOT NULL DEFAULT 3,
  notes VARCHAR(500) NULL,
  PRIMARY KEY (user_id, card_id),
  CONSTRAINT fk_wish_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_wish_card FOREIGN KEY (card_id) REFERENCES cards(card_id),
  CONSTRAINT fk_wish_art FOREIGN KEY (preferred_printing_id, card_id) REFERENCES printings(printing_id, card_id),
  CONSTRAINT ck_wish_qty CHECK (target_quantity BETWEEN 1 AND 1000),
  CONSTRAINT ck_wish_priority CHECK (priority BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Lista de desejos com quantidade alvo e arte opcional';

-- Identificadores estáveis dos capítulos do front
CREATE TABLE lessons (
  lesson_slug VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  title_pt VARCHAR(150) NOT NULL,
  position SMALLINT UNSIGNED NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Identificadores estáveis dos capítulos do front';

-- Progresso individual, sem simular autenticação no frontend
CREATE TABLE lesson_progress (
  user_id BIGINT UNSIGNED NOT NULL,
  lesson_slug VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  completed_at DATETIME(6) NULL,
  PRIMARY KEY (user_id, lesson_slug),
  CONSTRAINT fk_lp_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_lp_lesson FOREIGN KEY (lesson_slug) REFERENCES lessons(lesson_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Progresso individual, sem simular autenticação no frontend';

-- Arcos narrativos com fonte; carga inicial vazia
CREATE TABLE story_arcs (
  arc_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  name_pt VARCHAR(150) NOT NULL,
  source_url VARCHAR(2048) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Arcos narrativos com fonte; carga inicial vazia';

-- Fontes narrativas; capítulos e episódios não são inferidos da arte
CREATE TABLE narrative_references (
  reference_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference_key VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  medium VARCHAR(24) NOT NULL,
  title VARCHAR(255) NULL,
  chapter_number DECIMAL(7,2) NULL,
  episode_number SMALLINT UNSIGNED NULL,
  page_number SMALLINT UNSIGNED NULL,
  arc_id INT UNSIGNED NULL,
  source_url VARCHAR(2048) NOT NULL,
  notes TEXT NULL,
  CONSTRAINT fk_nr_arc FOREIGN KEY (arc_id) REFERENCES story_arcs(arc_id),
  CONSTRAINT ck_nr_medium CHECK (medium IN ('manga','anime','cover','original_art','other')),
  CONSTRAINT ck_nr_chapter CHECK (chapter_number IS NULL OR chapter_number > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Fontes narrativas; capítulos e episódios não são inferidos da arte';

-- Origem narrativa por arte, com revisão humana e responsável
CREATE TABLE printing_narrative_references (
  printing_id BIGINT UNSIGNED NOT NULL,
  reference_id BIGINT UNSIGNED NOT NULL,
  relation_kind VARCHAR(24) NOT NULL,
  verification_status VARCHAR(16) NOT NULL DEFAULT 'pending',
  explanation_pt TEXT NULL,
  verified_by BIGINT UNSIGNED NULL,
  verified_at DATETIME(6) NULL,
  PRIMARY KEY (printing_id, reference_id),
  CONSTRAINT fk_pnr_print FOREIGN KEY (printing_id) REFERENCES printings(printing_id),
  CONSTRAINT fk_pnr_ref FOREIGN KEY (reference_id) REFERENCES narrative_references(reference_id),
  CONSTRAINT fk_pnr_user FOREIGN KEY (verified_by) REFERENCES users(user_id),
  CONSTRAINT ck_pnr_relation CHECK (relation_kind IN ('depicts','inspired_by','cover','original_art','other')),
  CONSTRAINT ck_pnr_status CHECK (verification_status IN ('pending','verified','rejected')),
  CONSTRAINT ck_pnr_verified CHECK (verification_status <> 'verified' OR (verified_by IS NOT NULL AND verified_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Origem narrativa por arte, com revisão humana e responsável';

-- Diretório, sem garantia de estoque ou integração comercial
CREATE TABLE shops (
  shop_id SMALLINT UNSIGNED PRIMARY KEY,
  slug VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  website_url VARCHAR(2048) NOT NULL,
  region_code VARCHAR(16) NOT NULL,
  shop_kind VARCHAR(24) NOT NULL,
  links_checked_on DATE NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Diretório, sem garantia de estoque ou integração comercial';

-- Ofertas futuras de provedores autorizados; carga sem preços inventados
CREATE TABLE shop_offers (
  offer_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shop_id SMALLINT UNSIGNED NOT NULL,
  external_offer_id VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  card_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  printing_id BIGINT UNSIGNED NULL,
  offer_url VARCHAR(2048) NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  stock_quantity INT UNSIGNED NULL,
  checked_at DATETIME(6) NOT NULL,
  expires_at DATETIME(6) NULL,
  UNIQUE KEY uq_shop_offer (shop_id, external_offer_id),
  KEY ix_offer_price (card_id, currency, price),
  KEY ix_offer_expiry (expires_at),
  CONSTRAINT fk_offer_shop FOREIGN KEY (shop_id) REFERENCES shops(shop_id),
  CONSTRAINT fk_offer_card FOREIGN KEY (card_id) REFERENCES cards(card_id),
  CONSTRAINT fk_offer_art FOREIGN KEY (printing_id, card_id) REFERENCES printings(printing_id, card_id),
  CONSTRAINT ck_offer_price CHECK (price >= 0),
  CONSTRAINT ck_offer_expiry CHECK (expires_at IS NULL OR expires_at > checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Ofertas futuras de provedores autorizados; carga sem preços inventados';

-- Histórico monetário decimal, sempre com moeda e instante
CREATE TABLE price_observations (
  offer_id BIGINT UNSIGNED NOT NULL,
  observed_at DATETIME(6) NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  stock_quantity INT UNSIGNED NULL,
  PRIMARY KEY (offer_id, observed_at),
  CONSTRAINT fk_po_offer FOREIGN KEY (offer_id) REFERENCES shop_offers(offer_id),
  CONSTRAINT ck_po_price CHECK (price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Histórico monetário decimal, sempre com moeda e instante';

-- Auditoria sem senhas, tokens ou cookies no metadata
CREATE TABLE audit_events (
  event_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  entity_type VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  entity_key VARCHAR(160) NOT NULL,
  request_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  metadata JSON NULL,
  occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY ix_audit_entity (entity_type, entity_key, occurred_at),
  KEY ix_audit_actor (actor_user_id, occurred_at),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Auditoria sem senhas, tokens ou cookies no metadata';

-- Apoio editorial em português; não é tradução integral certificada
CREATE TABLE printing_explanations (
  printing_id BIGINT UNSIGNED NOT NULL,
  language_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pt-BR',
  summary TEXT NOT NULL,
  reading_guide TEXT NOT NULL,
  strategic_focus VARCHAR(255) NOT NULL,
  preview_text TEXT NOT NULL,
  is_curated BOOLEAN NOT NULL DEFAULT FALSE,
  details JSON NOT NULL,
  generator_version VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY (printing_id, language_code),
  CONSTRAINT fk_pe_print FOREIGN KEY (printing_id) REFERENCES printings(printing_id),
  CONSTRAINT fk_pe_language FOREIGN KEY (language_code) REFERENCES languages(language_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Apoio editorial em português; não é tradução integral certificada';

INSERT INTO schema_migrations(version,description) VALUES ('001_initial_mysql84','Catálogo, usuários, decks, coleção, narrativa e integrações');
