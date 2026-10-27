-- Consultas de conferência, somente leitura. Execute após install.sql.
USE grand_line_atlas;
SELECT 'cards' AS entity,COUNT(*) AS actual,3123 AS expected FROM cards
UNION ALL SELECT 'printings',COUNT(*),5343 FROM printings
UNION ALL SELECT 'images',COUNT(*),5343 FROM printing_assets
UNION ALL SELECT 'descriptions_pt',COUNT(*),5343 FROM printing_explanations
UNION ALL SELECT 'catalog_groups',COUNT(*),65 FROM catalog_groups;
SELECT source_id,COUNT(*) AS printings,COUNT(DISTINCT card_id) AS card_ids FROM printings GROUP BY source_id;
SELECT physical_language,COUNT(*) AS printings FROM v_catalog_printings GROUP BY physical_language;
SELECT c.card_id,ct.name,COUNT(p.printing_id) AS versions FROM cards c
JOIN card_texts ct ON ct.card_id=c.card_id AND ct.language_code='en'
JOIN printings p ON p.card_id=c.card_id WHERE c.card_id='OP01-025' GROUP BY c.card_id,ct.name;
SELECT TABLE_NAME,TABLE_COMMENT FROM information_schema.TABLES
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;
