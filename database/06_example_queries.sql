-- Exemplos com parâmetros de sessão para estudo no MySQL.
-- Na API/PDO substituir pelos placeholders de consultas preparadas.
USE grand_line_atlas;

-- Nome/personagem + cor + tipo + custo (quatro filtros independentes).
SET @name='%Luffy%';
SET @color='Red';
SET @type='CHARACTER';
SET @max_cost=5;
SELECT v.* FROM v_catalog_cards v
WHERE v.name LIKE @name AND v.type_code=@type AND v.cost<=@max_cost
AND EXISTS (SELECT 1 FROM card_colors cc WHERE cc.card_id=v.card_id AND cc.color_code=@color)
ORDER BY v.card_id LIMIT 24;

-- Combinar TODOS os grupos: Herói + Chapéu de Palha + Pirata + D.
-- Tags editoriais não são características oficiais de jogo.
SELECT c.card_id,t.name FROM cards c
JOIN card_texts t ON t.card_id=c.card_id AND t.language_code='en'
WHERE c.type_code IN ('LEADER','CHARACTER') AND t.name LIKE '%Luffy%'
AND c.card_id IN (
  SELECT card_id FROM card_discovery_tags
  WHERE tag_code IN ('heroes','straw-hat','pirate','d-family')
  GROUP BY card_id HAVING COUNT(DISTINCT tag_code)=4
) ORDER BY c.card_id;

-- QUALQUER grupo: usar EXISTS com IN, sem HAVING de contagem.
SELECT v.card_id,v.name FROM v_catalog_cards v
WHERE EXISTS (SELECT 1 FROM card_discovery_tags t WHERE t.card_id=v.card_id AND t.tag_code IN ('marine','revolutionary'))
ORDER BY v.card_id LIMIT 24;

-- Impressões de um ID e seus produtos (sem multiplicar cartas na listagem principal).
SELECT p.legacy_uid,p.rarity_code,p.block_code,p.physical_language,p.text_language,
       p.image_local_path,p.source_url,p.description_pt
FROM v_catalog_printings p WHERE p.card_id='OP01-025' ORDER BY p.printing_id;

-- Produto/coleção exato + característica impressa + palavra citada no texto.
SET @group_key='569101';
SET @trait='Straw Hat Crew';
SET @keyword='rush';
SELECT p.legacy_uid,p.card_id,p.name FROM v_catalog_printings p
WHERE EXISTS (SELECT 1 FROM printing_groups pg JOIN catalog_groups g ON g.group_id=pg.group_id WHERE pg.printing_id=p.printing_id AND g.catalog_key=@group_key)
AND EXISTS (SELECT 1 FROM printing_traits pt JOIN traits t ON t.trait_id=pt.trait_id WHERE pt.printing_id=p.printing_id AND t.source_name=@trait COLLATE utf8mb4_0900_as_cs)
AND EXISTS (SELECT 1 FROM printing_keyword_mentions km WHERE km.printing_id=p.printing_id AND km.keyword_code=@keyword)
ORDER BY p.legacy_uid;

-- Ficha completa original e proveniência de campos complementados.
SELECT legacy_uid,raw_payload,JSON_EXTRACT(raw_payload,'$.fieldSources') AS field_sources
FROM printings WHERE legacy_uid='OP03-072';

-- API deve extrair user_id da sessão autenticada, nunca confiar no parâmetro do cliente.
SET @user_id=0;
SET @deck_id=0;
SELECT * FROM v_deck_summary WHERE user_id=@user_id ORDER BY deck_id;
SELECT i.* FROM v_deck_issues i JOIN decks d ON d.deck_id=i.deck_id WHERE i.deck_id=@deck_id AND d.user_id=@user_id;
SELECT * FROM v_deck_cost_curve c WHERE c.deck_id=@deck_id AND EXISTS (SELECT 1 FROM decks d WHERE d.deck_id=c.deck_id AND d.user_id=@user_id);
SELECT * FROM v_deck_shopping_missing WHERE deck_id=@deck_id AND user_id=@user_id AND missing_quantity>0;

-- Posses por ID, somando artes/condições, preservando a escolha de impressão no detalhe.
SELECT * FROM v_collection_totals WHERE user_id=@user_id;

-- Referências de arte somente após revisão humana.
SELECT p.legacy_uid,r.medium,r.chapter_number,r.episode_number,r.source_url,pr.explanation_pt
FROM printing_narrative_references pr JOIN printings p ON p.printing_id=pr.printing_id
JOIN narrative_references r ON r.reference_id=pr.reference_id
WHERE p.card_id='OP01-025' AND pr.verification_status='verified';
