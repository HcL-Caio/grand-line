-- Vocabulários e diretório; carga inicial em schema novo.
SET NAMES utf8mb4;
SET @atlas_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION,NO_BACKSLASH_ESCAPES';
SET time_zone = '+00:00';
START TRANSACTION;

INSERT INTO data_sources (source_id, code, name, base_url, is_official, attribution) VALUES
(1, 'bandai-en', 'Bandai · catálogo inglês', 'https://en.onepiece-cardgame.com/cardlist/', 1, 'One Piece © Eiichiro Oda / Shueisha / Toei Animation; Card Game © Bandai.'),
(2, 'oplay', 'OPlayTCG · complemento', 'https://oplaytcg.com/en/library', 0, 'Catálogo comunitário. Créditos das imagens pertencem aos titulares da obra e do jogo.');

INSERT INTO languages (language_code, name_pt) VALUES
('und', 'Não informado'),
('en', 'Inglês'),
('pt-BR', 'Português brasileiro'),
('ja', 'Japonês'),
('zh-Hans', 'Chinês simplificado'),
('zh-Hant', 'Chinês tradicional'),
('ko', 'Coreano'),
('th', 'Tailandês');

INSERT INTO card_types (type_code, name_pt, deck_zone) VALUES
('LEADER', 'Líder', 'leader'),
('CHARACTER', 'Personagem', 'main'),
('EVENT', 'Evento', 'main'),
('STAGE', 'Cenário', 'main'),
('DON', 'DON!!', 'resource');

INSERT INTO colors (color_code, name_pt, hex_color) VALUES
('Red', 'Vermelho', '#dc655c'),
('Green', 'Verde', '#70b68d'),
('Blue', 'Azul', '#659ecb'),
('Purple', 'Roxo', '#ac84cf'),
('Black', 'Preto', '#a3a9ac'),
('Yellow', 'Amarelo', '#e7c96c');

INSERT INTO attributes (attribute_code, name_pt) VALUES
('Slash', 'Corte'),
('Strike', 'Golpe'),
('Ranged', 'À distância'),
('Special', 'Especial'),
('Wisdom', 'Sabedoria');

INSERT INTO rarities (rarity_code, name_pt) VALUES
('C', 'Comum'),
('DON', 'DON!!'),
('L', 'Líder'),
('P', 'Promocional'),
('R', 'Rara'),
('SEC', 'Secreta'),
('SP CARD', 'Especial'),
('SR', 'Super-rara'),
('TR', 'Treasure Rare'),
('UC', 'Incomum');

INSERT INTO roles (role_code, name_pt) VALUES
('member', 'Membro'),
('editor', 'Editor de conteúdo'),
('admin', 'Administrador');

INSERT INTO play_formats (format_code, name_pt, description) VALUES
('study', 'Estudo / casual', 'Revisão da estrutura básica. Não certifica elegibilidade competitiva.'),
('standard', 'Standard', 'Consultar regulamento, idiomas, blocos e lista de restrições vigentes do evento.');

INSERT INTO card_conditions (condition_code, name_pt) VALUES
('unknown', 'Não informada'),
('mint', 'Mint'),
('near_mint', 'Near Mint'),
('excellent', 'Excellent'),
('good', 'Good'),
('played', 'Played'),
('damaged', 'Danificada');

INSERT INTO discovery_tags (tag_code, name_pt, classification_kind, description) VALUES
('straw-hat', 'Chapéu de Palha', 'printed', 'Característica Straw Hat Crew'),
('pirate', 'Piratas', 'editorial', 'Grupos piratas e ex-piratas indicados nas características'),
('marine', 'Marinha', 'printed', 'Característica Navy'),
('revolutionary', 'Revolucionários', 'printed', 'Característica Revolutionary Army'),
('d-family', 'Nome com “D.”', 'editorial', 'Líderes e Personagens com D. no nome publicado'),
('heroes', 'Heróis / aliados', 'editorial', 'Seleção editorial de protagonistas e aliados; varia por arco'),
('antagonists', 'Antagonistas', 'editorial', 'Personagens que atuam como antagonistas em arcos; não é classificação moral fixa'),
('warlords', 'Shichibukai', 'printed', 'Característica The Seven Warlords of the Sea'),
('emperors', 'Imperadores', 'printed', 'Característica The Four Emperors'),
('wano', 'Wano', 'printed', 'Característica Land of Wano'),
('egghead', 'Egghead', 'printed', 'Característica Egghead');

INSERT INTO keywords (keyword_code, name_en, name_pt, category) VALUES
('rush', 'Rush', 'Investida', 'keyword'),
('blocker', 'Blocker', 'Bloqueador', 'keyword'),
('banish', 'Banish', 'Banir', 'keyword'),
('double-attack', 'Double Attack', 'Ataque duplo', 'keyword'),
('on-play', 'On Play', 'Ao entrar em jogo', 'timing'),
('when-attacking', 'When Attacking', 'Ao declarar ataque', 'timing'),
('activate-main', 'Activate: Main', 'Ativar: principal', 'timing'),
('main', 'Main', 'Fase principal', 'timing'),
('counter', 'Counter', 'Counter', 'timing'),
('on-ko', 'On K.O.', 'Ao sofrer K.O.', 'timing'),
('end-of-your-turn', 'End of Your Turn', 'Fim do seu turno', 'timing'),
('once-per-turn', 'Once Per Turn', 'Uma vez por turno', 'timing');

INSERT INTO lessons (lesson_slug, title_pt, position) VALUES
('conceitos', 'O começo da aventura', 1),
('tipos', 'Os cinco tipos de carta', 2),
('anatomia', 'Como ler uma carta', 3),
('cores', 'As seis cores', 4),
('deck', 'Montando seu primeiro deck', 5),
('campo', 'Conhecendo o campo', 6),
('preparacao', 'Preparando a partida', 7),
('turno', 'As cinco fases do turno', 8),
('don', 'DON!!: seu principal recurso', 9),
('combate', 'Ataque, bloqueio e defesa', 10),
('habilidades', 'Palavras-chave e ativações', 11),
('efeitos', 'Efeitos e dúvidas frequentes', 12),
('formatos', 'Formatos, versões e fontes', 13),
('pratica', 'Teste seus conhecimentos', 14);

INSERT INTO characters (character_id, slug, canonical_name, verification_status) VALUES
(1, 'monkey-d-luffy', 'Monkey.D.Luffy', 'pending'),
(2, 'roronoa-zoro', 'Roronoa Zoro', 'pending'),
(3, 'nami', 'Nami', 'pending'),
(4, 'usopp', 'Usopp', 'pending'),
(5, 'sanji', 'Sanji', 'pending'),
(6, 'tony-tony-chopper', 'Tony Tony.Chopper', 'pending'),
(7, 'nico-robin', 'Nico Robin', 'pending'),
(8, 'franky', 'Franky', 'pending'),
(9, 'brook', 'Brook', 'pending'),
(10, 'jinbe', 'Jinbe', 'pending');

INSERT INTO character_aliases (character_id, language_code, alias) VALUES
(1, 'en', 'Monkey.D.Luffy'),
(2, 'en', 'Roronoa Zoro'),
(3, 'en', 'Nami'),
(4, 'en', 'Usopp'),
(5, 'en', 'Sanji'),
(6, 'en', 'Tony Tony.Chopper'),
(7, 'en', 'Nico Robin'),
(8, 'en', 'Franky'),
(9, 'en', 'Brook'),
(10, 'en', 'Jinbe');

INSERT INTO shops (shop_id, slug, name, website_url, region_code, shop_kind, links_checked_on) VALUES
(1, 'myp-cards', 'MYP Cards', 'https://mypcards.com/onepiece', 'BR', 'marketplace', '2026-10-23'),
(2, 'universo-cards', 'Universo Cards', 'https://universocards.com.br/collections/one-piece-singles', 'BR', 'store', '2026-10-23'),
(3, 'collecta-tcg', 'Collecta TCG', 'https://www.collectatcg.com.br/', 'BR', 'store', '2026-10-23'),
(4, 'arena-cwg', 'Arena CWG', 'https://www.arenacwg.com/onepiece', 'BR', 'store', '2026-10-23'),
(5, 'tcgplayer', 'TCGplayer', 'https://www.tcgplayer.com/categories/trading-and-collectible-card-games/one-piece-card-game', 'global', 'marketplace', '2026-10-23');

COMMIT;
SET SESSION sql_mode = @atlas_previous_sql_mode;
