-- Views de leitura, sem DEFINER privilegiado. A aplicação ainda deve filtrar por dono.
SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;

CREATE SQL SECURITY INVOKER VIEW v_catalog_cards AS
SELECT c.card_id, c.type_code, t.name, t.effect_text, t.trigger_text,
       c.cost, c.power, c.counter_value, c.life, c.deck_copy_limit,
       c.is_preview, c.first_known_release_date,
       (SELECT COUNT(*) FROM printings p WHERE p.card_id=c.card_id AND p.archived_at IS NULL) AS printing_count,
       COALESCE((SELECT JSON_ARRAYAGG(cc.color_code) FROM card_colors cc WHERE cc.card_id=c.card_id),JSON_ARRAY()) AS colors
FROM cards c JOIN card_texts t ON t.card_id=c.card_id AND t.language_code='en'
WHERE c.archived_at IS NULL;

CREATE SQL SECURITY INVOKER VIEW v_catalog_printings AS
SELECT p.printing_id, p.legacy_uid, p.card_id, c.type_code,
       p.language_code AS physical_language, t.language_code AS text_language,
       t.name, t.effect_text, t.trigger_text, p.rarity_code, p.block_code,
       p.printed_cost AS cost, p.printed_power AS power, p.printed_counter AS counter_value,
       p.printed_life AS life, p.is_preview, p.image_status, p.release_date,
       s.code AS source_code, p.source_url, a.remote_url AS image_url, a.local_path AS image_local_path,
       e.summary AS description_pt, e.reading_guide AS reading_pt, e.strategic_focus AS focus_pt
FROM printings p
JOIN cards c ON c.card_id=p.card_id
JOIN printing_texts t ON t.printing_id=p.printing_id AND t.language_code='en'
JOIN data_sources s ON s.source_id=p.source_id
LEFT JOIN printing_assets a ON a.printing_id=p.printing_id AND a.asset_role='front'
LEFT JOIN printing_explanations e ON e.printing_id=p.printing_id AND e.language_code='pt-BR'
WHERE p.archived_at IS NULL AND c.archived_at IS NULL;

CREATE SQL SECURITY INVOKER VIEW v_collection_totals AS
SELECT user_id, card_id, SUM(quantity) AS owned_quantity
FROM collection_entries GROUP BY user_id, card_id;

CREATE SQL SECURITY INVOKER VIEW v_deck_main_totals AS
SELECT deck_id, SUM(quantity) AS main_quantity,
       SUM(CASE WHEN c.type_code='CHARACTER' THEN i.quantity ELSE 0 END) AS character_quantity,
       SUM(CASE WHEN c.type_code='EVENT' THEN i.quantity ELSE 0 END) AS event_quantity,
       SUM(CASE WHEN c.type_code='STAGE' THEN i.quantity ELSE 0 END) AS stage_quantity,
       SUM(CASE WHEN c.counter_value>0 THEN i.quantity ELSE 0 END) AS counter_quantity
FROM deck_items i JOIN cards c ON c.card_id=i.card_id GROUP BY deck_id;

CREATE SQL SECURITY INVOKER VIEW v_deck_summary AS
SELECT d.deck_id,d.public_id,d.user_id,d.name,d.format_code,d.leader_card_id,d.status,d.revision,
       COALESCE(t.main_quantity,0) AS main_quantity,d.don_quantity,
       COALESCE(t.character_quantity,0) AS character_quantity,
       COALESCE(t.event_quantity,0) AS event_quantity,
       COALESCE(t.stage_quantity,0) AS stage_quantity,
       COALESCE(t.counter_quantity,0) AS counter_quantity
FROM decks d LEFT JOIN v_deck_main_totals t ON t.deck_id=d.deck_id WHERE d.deleted_at IS NULL;

-- Verificação estrutural, NÃO certificação de Standard, idiomas ou lista competitiva vigente.
-- CHECK só cobre uma linha. As condições abaixo exigem consultas entre várias linhas.
CREATE SQL SECURITY INVOKER VIEW v_deck_issues AS
SELECT d.deck_id,'leader_required' AS issue_code,CAST(NULL AS CHAR(32)) AS card_id,
       'Escolha uma carta da categoria Líder.' AS message
FROM decks d LEFT JOIN cards c ON c.card_id=d.leader_card_id
WHERE d.deleted_at IS NULL AND (c.card_id IS NULL OR c.type_code<>'LEADER')
UNION ALL
SELECT d.deck_id,'main_count',NULL,'O deck principal precisa ter exatamente 50 cartas.'
FROM decks d LEFT JOIN v_deck_main_totals t ON t.deck_id=d.deck_id
WHERE d.deleted_at IS NULL AND COALESCE(t.main_quantity,0)<>50
UNION ALL
SELECT d.deck_id,'don_count',NULL,'O deck de recursos precisa ter exatamente dez DON!!.'
FROM decks d WHERE d.deleted_at IS NULL AND d.don_quantity<>10
UNION ALL
SELECT d.deck_id,'don_type',d.don_card_id,'A arte de recurso selecionada precisa pertencer a um DON!!.'
FROM decks d JOIN cards c ON c.card_id=d.don_card_id
WHERE d.deleted_at IS NULL AND c.type_code<>'DON'
UNION ALL
SELECT d.deck_id,'main_type',i.card_id,'Apenas Personagens, Eventos e Cenários entram no deck principal.'
FROM decks d JOIN deck_items i ON i.deck_id=d.deck_id JOIN cards c ON c.card_id=i.card_id
WHERE d.deleted_at IS NULL AND c.type_code NOT IN ('CHARACTER','EVENT','STAGE')
UNION ALL
SELECT d.deck_id,'copy_limit',i.card_id,'Quantidade excede o limite por ID registrado nesta fotografia.'
FROM decks d JOIN deck_items i ON i.deck_id=d.deck_id JOIN cards c ON c.card_id=i.card_id
WHERE d.deleted_at IS NULL AND i.quantity>c.deck_copy_limit
UNION ALL
SELECT d.deck_id,'color_mismatch',i.card_id,'A carta tem uma cor ausente no Líder.'
FROM decks d JOIN deck_items i ON i.deck_id=d.deck_id
WHERE d.deleted_at IS NULL AND d.leader_card_id IS NOT NULL AND EXISTS (
  SELECT 1 FROM card_colors cc WHERE cc.card_id=i.card_id
  AND NOT EXISTS (SELECT 1 FROM card_colors lc WHERE lc.card_id=d.leader_card_id AND lc.color_code=cc.color_code)
);

CREATE SQL SECURITY INVOKER VIEW v_deck_cost_curve AS
SELECT i.deck_id,CASE WHEN c.cost>=6 THEN 6 ELSE c.cost END AS cost_bucket,SUM(i.quantity) AS quantity
FROM deck_items i JOIN cards c ON c.card_id=i.card_id
WHERE c.cost IS NOT NULL GROUP BY i.deck_id,CASE WHEN c.cost>=6 THEN 6 ELSE c.cost END;

CREATE SQL SECURITY INVOKER VIEW v_deck_requirements AS
SELECT d.deck_id,d.user_id,i.card_id,'main' AS deck_zone,i.quantity AS required_quantity
FROM decks d JOIN deck_items i ON i.deck_id=d.deck_id WHERE d.deleted_at IS NULL
UNION ALL
SELECT d.deck_id,d.user_id,d.leader_card_id,'leader',1
FROM decks d WHERE d.deleted_at IS NULL AND d.leader_card_id IS NOT NULL
UNION ALL
SELECT d.deck_id,d.user_id,d.don_card_id,'resource',d.don_quantity
FROM decks d WHERE d.deleted_at IS NULL AND d.don_quantity>0;

CREATE SQL SECURITY INVOKER VIEW v_deck_shopping_missing AS
SELECT r.deck_id,r.user_id,r.card_id,r.deck_zone,r.required_quantity,
       CASE WHEN r.deck_zone='resource' AND r.card_id IS NULL
         THEN COALESCE((SELECT SUM(o.owned_quantity) FROM v_collection_totals o JOIN cards c ON c.card_id=o.card_id WHERE o.user_id=r.user_id AND c.type_code='DON'),0)
         ELSE COALESCE(o.owned_quantity,0) END AS owned_quantity,
       GREATEST(0,CAST(r.required_quantity AS SIGNED)-CAST(CASE WHEN r.deck_zone='resource' AND r.card_id IS NULL
         THEN COALESCE((SELECT SUM(o2.owned_quantity) FROM v_collection_totals o2 JOIN cards c2 ON c2.card_id=o2.card_id WHERE o2.user_id=r.user_id AND c2.type_code='DON'),0)
         ELSE COALESCE(o.owned_quantity,0) END AS SIGNED)) AS missing_quantity
FROM v_deck_requirements r LEFT JOIN v_collection_totals o ON o.user_id=r.user_id AND o.card_id=r.card_id;
