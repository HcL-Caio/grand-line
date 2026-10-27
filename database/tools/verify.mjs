// Verificação real via cliente MySQL. Sem dependências npm.
import fs from 'node:fs';import path from 'node:path';import cp from 'node:child_process';import assert from 'node:assert/strict';import {fileURLToPath} from 'node:url';
const dir=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..'),report=JSON.parse(fs.readFileSync(path.join(dir,'docs/IMPORTACAO.json')));
const db=process.env.DB_NAME||'grand_line_atlas';if(!/^[A-Za-z0-9_]+$/.test(db))throw Error('DB_NAME inválido');
const exe=process.env.MYSQL_BIN||'mysql';const flags=['--no-defaults','--user='+(process.env.DB_USER||'atlas_app'),'--default-character-set=utf8mb4','--batch','--raw','--skip-column-names','--connect-timeout=5'];
if(process.env.DB_PROTOCOL==='MEMORY')flags.push('--protocol=MEMORY','--host=localhost','--shared-memory-base-name='+process.env.DB_SHARED_MEMORY_NAME);
else flags.push('--protocol=TCP','--host='+(process.env.DB_HOST||'127.0.0.1'),'--port='+(process.env.DB_PORT||'3306'));
const checks=[];function query(sql){return cp.spawnSync(exe,[...flags,'--execute=USE `'+db+'`; SET time_zone=\'+00:00\'; '+sql],{encoding:'utf8',windowsHide:true,env:{...process.env,MYSQL_PWD:process.env.DB_PASSWORD||''},timeout:60000,maxBuffer:64000000});}
function run(sql){const r=query(sql);if(r.status!==0)throw Error((r.error?.message||'')+' '+(r.stderr||'Falha no cliente MySQL').slice(0,4000));return r.stdout.trim();}
function equal(name,sql,expected){assert.equal(run(sql),String(expected),name);checks.push(name);}
function rejected(name,sql,code){const r=query('START TRANSACTION; '+sql+'; ROLLBACK;');assert.notEqual(r.status,0,name);assert.ok(r.stderr.includes('ERROR '+code+' '),name+': '+r.stderr);checks.push(name);}
const version=run('SELECT VERSION()');assert.ok(/^8\./.test(version),'Use MySQL 8.4 LTS; MariaDB não é equivalente');
for(const[table,count]of Object.entries(report.tablesWithSeedData))equal('Carga '+table,'SELECT COUNT(*) FROM '+table,count);
equal('51 tabelas',"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE'",51);
equal('9 views',"SELECT COUNT(*) FROM information_schema.views WHERE table_schema=DATABASE()",9);
equal('IDs com vínculo órfão','SELECT COUNT(*) FROM printings p LEFT JOIN cards c ON c.card_id=p.card_id WHERE c.card_id IS NULL',0);
equal('UID preservado no original',"SELECT COUNT(*) FROM printings WHERE legacy_uid<>JSON_UNQUOTE(JSON_EXTRACT(raw_payload,'$.uid'))",0);
equal('ID preservado no original',"SELECT COUNT(*) FROM printings WHERE card_id<>JSON_UNQUOTE(JSON_EXTRACT(raw_payload,'$.id'))",0);
equal('Zero não vira NULL',"SELECT cost FROM cards WHERE card_id='OP03-072'",0);
equal('Desconhecido não vira zero',"SELECT cost IS NULL FROM cards WHERE card_id='OP01-001'",1);
equal('Vocabulários com grafias preservadas',"SELECT COUNT(*) FROM traits WHERE source_name COLLATE utf8mb4_0900_as_cs IN ('SMILE','Smile')",2);
equal('Todas as fotos têm SHA-256',"SELECT COUNT(*) FROM printing_assets WHERE file_sha256 IS NULL OR byte_size<1000 OR local_path IS NULL",0);
equal('View de cartas não duplica IDs','SELECT COUNT(*) FROM v_catalog_cards',report.distinctCardIds);
equal('View de impressões não duplica UIDs','SELECT COUNT(*) FROM v_catalog_printings',report.printings);
equal('Idioma inglês independente da impressão',"SELECT COUNT(*) FROM printing_texts WHERE language_code<>'en'",0);
equal('Nenhum usuário de demonstração','SELECT COUNT(*) FROM users',0);
equal('Nenhuma origem narrativa inventada','SELECT COUNT(*) FROM narrative_references',0);
equal('Nenhuma oferta fictícia','SELECT COUNT(*) FROM shop_offers',0);
equal('Todos os grupos do Luffy',"SELECT COUNT(*)>0 FROM (SELECT t.card_id FROM card_discovery_tags t JOIN card_texts n ON n.card_id=t.card_id AND n.language_code='en' WHERE n.name LIKE '%Luffy%' AND t.tag_code IN ('heroes','straw-hat','pirate','d-family') GROUP BY t.card_id HAVING COUNT(DISTINCT t.tag_code)=4) x",1);
const catalogPath=path.resolve(dir,'../dist/catalog-data.json');if(fs.existsSync(catalogPath)){const expected=JSON.parse(fs.readFileSync(catalogPath)).cards;const actual=JSON.parse(run('SELECT JSON_ARRAYAGG(JSON_OBJECT(\'uid\',legacy_uid,\'payload\',raw_payload)) FROM printings'));const byUid=new Map(actual.map(r=>[r.uid,r.payload]));for(const c of expected)assert.deepEqual(byUid.get(c.uid),c,'Registro integral '+c.uid);checks.push('Round-trip integral de '+expected.length+' registros JSON');}
if(process.argv.includes('--write-tests')){
 rejected('FK recusa ID de carta inexistente',"INSERT INTO card_colors(card_id,color_code) VALUES ('QA-NOT-EXISTS','Red')",1452);
 rejected('FK recusa tipo inexistente',"INSERT INTO cards(card_id,type_code,canonical_source_id) VALUES ('QA-INVALID','INVALID',1)",1452);
 rejected('ID público único',"INSERT INTO cards(card_id,type_code,canonical_source_id) VALUES ('OP01-025','CHARACTER',1)",1062);
 rejected('Custo negativo recusado',"INSERT INTO cards(card_id,type_code,cost,canonical_source_id) VALUES ('QA-NEGATIVE','CHARACTER',-1,1)",1264);
 rejected('Copias máximas inválidas',"INSERT INTO cards(card_id,type_code,deck_copy_limit,canonical_source_id) VALUES ('QA-ZERO-LIMIT','CHARACTER',0,1)",3819);
 const user="SET @qa_uuid=UUID(); INSERT INTO users(public_id,username,email,display_name,password_hash,status) VALUES (@qa_uuid,CONCAT('qa_',REPLACE(@qa_uuid,'-','')),CONCAT(@qa_uuid,'@example.invalid'),'QA rollback','not-a-login-hash','active'); SET @qa_user=LAST_INSERT_ID(); ";
 const deck=user+"INSERT INTO decks(public_id,user_id,name,leader_card_id) VALUES(UUID(),@qa_user,'QA rollback','OP01-001'); SET @qa_deck=LAST_INSERT_ID(); ";
 const item="INSERT INTO deck_items(deck_id,card_id,quantity) VALUES (@qa_deck,'OP01-025',4); ";
 rejected('Quantidade zero no deck',deck+"INSERT INTO deck_items(deck_id,card_id,quantity) VALUES (@qa_deck,'OP01-025',0)",3819);
 rejected('Uma linha por ID no deck',deck+item+item,1062);
 rejected('Arte de outra carta no deck',deck+"INSERT INTO deck_items(deck_id,card_id,quantity,preferred_printing_id) SELECT @qa_deck,'OP01-025',1,printing_id FROM printings WHERE legacy_uid='OP01-002'",1452);
 rejected('Arte de outro Líder',user+"INSERT INTO decks(public_id,user_id,name,leader_card_id,leader_printing_id) SELECT UUID(),@qa_user,'QA','OP01-001',printing_id FROM printings WHERE legacy_uid='OP01-002'",1452);
 rejected('Quantidade zero na coleção',user+"INSERT INTO collection_entries(user_id,card_id,quantity) VALUES(@qa_user,'OP01-025',0)",3819);
 const own="INSERT INTO collection_entries(user_id,card_id,quantity) VALUES(@qa_user,'OP01-025',4); ";
 rejected('NULL de impressão não permite duplicar posse',user+own+own,1062);
 rejected('Origem de impressão precisa corresponder à carga',"INSERT INTO printings(legacy_uid,card_id,language_code,source_language_code,rarity_code,source_id,import_run_id,source_url,image_status,raw_payload) VALUES('QA-MISMATCH','OP01-025','en','en','SR',2,1,'https://example.invalid','unknown',JSON_OBJECT())",1452);
 rejected('Preço negativo',"INSERT INTO shop_offers(shop_id,external_offer_id,card_id,offer_url,price,currency,checked_at) VALUES(1,'QA','OP01-025','https://example.invalid',-1,'BRL',UTC_TIMESTAMP(6))",3819);
 rejected('Token vencendo antes de existir',user+"INSERT INTO user_sessions(token_hash,user_id,created_at,expires_at) VALUES(UNHEX(SHA2(UUID(),256)),@qa_user,'2026-10-26','2026-10-25')",3819);
 function transaction(name,body,expected){equal(name,'START TRANSACTION; '+deck+body+'; ROLLBACK;',expected);}
 transaction('Rascunho acusa 50 cartas ausentes',"SELECT COUNT(*) FROM v_deck_issues WHERE deck_id=@qa_deck AND issue_code='main_count'",1);
 transaction('Cinco cópias comuns geram erro de estrutura',"INSERT INTO deck_items(deck_id,card_id,quantity) VALUES(@qa_deck,'OP01-025',5); SELECT COUNT(*) FROM v_deck_issues WHERE deck_id=@qa_deck AND issue_code='copy_limit'",1);
 transaction('Cor incompatível é detectada',"INSERT INTO deck_items(deck_id,card_id,quantity) VALUES(@qa_deck,'OP01-077',1); SELECT COUNT(*) FROM v_deck_issues WHERE deck_id=@qa_deck AND issue_code='color_mismatch'",1);
 const full="INSERT INTO deck_items(deck_id,card_id,quantity) SELECT @qa_deck,x.card_id,IF(x.rn=13,2,4) FROM (SELECT c.card_id,ROW_NUMBER() OVER(ORDER BY c.card_id) AS rn FROM cards c WHERE c.type_code IN ('CHARACTER','EVENT','STAGE') AND c.cost IS NOT NULL AND c.is_preview=FALSE AND c.deck_copy_limit>=4 AND EXISTS(SELECT 1 FROM card_colors cc WHERE cc.card_id=c.card_id AND cc.color_code='Red') AND NOT EXISTS(SELECT 1 FROM card_colors cc WHERE cc.card_id=c.card_id AND cc.color_code<>'Red'))x WHERE x.rn<=13; ";
 transaction('Deck completo tem cinquenta cartas',full+'SELECT main_quantity FROM v_deck_summary WHERE deck_id=@qa_deck',50);
 transaction('Deck completo passa estrutura básica',full+'SELECT COUNT(*) FROM v_deck_issues WHERE deck_id=@qa_deck',0);
 transaction('Lista inclui Líder, cinquenta e dez DON',full+'SELECT SUM(missing_quantity) FROM v_deck_shopping_missing WHERE deck_id=@qa_deck',61);
 transaction('Curva soma as cinquenta cartas',full+'SELECT SUM(quantity) FROM v_deck_cost_curve WHERE deck_id=@qa_deck',50);
 transaction('Coleção desconta cartas faltantes',full+"INSERT INTO collection_entries(user_id,card_id,quantity) SELECT @qa_user,card_id,4 FROM deck_items WHERE deck_id=@qa_deck AND quantity=4 LIMIT 1; SELECT SUM(missing_quantity) FROM v_deck_shopping_missing WHERE deck_id=@qa_deck",57);
 transaction('DON genérico considera recursos possuídos',full+"INSERT INTO collection_entries(user_id,card_id,quantity) SELECT @qa_user,card_id,2 FROM cards WHERE type_code='DON' LIMIT 1; SELECT SUM(missing_quantity) FROM v_deck_shopping_missing WHERE deck_id=@qa_deck",59);
 equal('Todos os usuários de teste revertidos','SELECT COUNT(*) FROM users',0);
}
const result={status:'PASS',mysqlVersion:version,checkedAt:new Date().toISOString(),checks:checks.length,writeTests:process.argv.includes('--write-tests'),details:checks};
if(process.env.ATLAS_VERIFY_REPORT)fs.writeFileSync(process.env.ATLAS_VERIFY_REPORT,JSON.stringify(result,null,2)+'\n');console.log(JSON.stringify(result));
