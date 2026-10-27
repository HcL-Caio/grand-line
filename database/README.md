# Banco MySQL · Grand Line Atlas

O banco está pronto para importação em **MySQL 8.4 LTS**. A versão testada foi MySQL Community Server 8.4.10. A carga acompanha **3.123 IDs, 5.343 impressões/artes, 5.343 vínculos de imagem e 5.343 explicações em português**, extraídos da fotografia do catálogo de **23/10/2026**. A estrutura foi preparada em 26/10/2026.

São 51 tabelas: entidades de negócio, vocabulários e relações N:N. Elas cobrem catálogo, filtros, usuários, decks, coleção, aprendizagem, pesquisa narrativa e futuras integrações. O frontend continua usando arquivos estáticos; esta entrega não ativa autenticação nem endpoints PHP.

## Instalar

Use MySQL 8.4, por exemplo com MySQL Workbench. **MariaDB incluído em algumas instalações do XAMPP não é MySQL 8.4**; este SQL não foi validado nele. A collation `utf8mb4_0900_ai_ci`, JSON, colunas geradas e CHECKs fazem parte do alvo escolhido. Não use MySQL 5.7, que não oferece as mesmas garantias.

1. Abra uma conexão com permissão para criar banco/tabelas.
2. No Workbench, abra `install.sql` e execute o arquivo completo. Ele cria e seleciona `grand_line_atlas`.
3. Execute `05_verify.sql` para conferir as contagens e a estrutura.
4. Explore `06_example_queries.sql` com buscas, quatro grupos combinados e consultas de deck/coleção.

Alternativa pelo cliente MySQL, inclusive no Windows:

```text
mysql --default-character-set=utf8mb4 -u root -p
mysql> source C:/CAMINHO/DO/PROJETO/database/install.sql
mysql> source C:/CAMINHO/DO/PROJETO/database/05_verify.sql
```

Digite sua senha no prompt. Substitua o caminho pela pasta que você extraiu. O caminho pode ser escrito com `/` no Windows. Caso prefira executar por etapas, a ordem é `00_create_database.sql`, `01_schema.sql`, `02_reference_data.sql`, `03_catalog_data.sql`, `04_views.sql`.

**Importação inicial, uma única vez em schema vazio.** Os arquivos não contêm DROP, TRUNCATE, REPLACE nem desativação de chaves estrangeiras. Se já existir um banco com tabelas/dados, faça backup e use uma conexão/schema de desenvolvimento separado; não reaplique a carga. DDL do MySQL possui commits implícitos: um erro na estrutura pode deixar a instalação parcial. As cargas de referência e catálogo usam transações próprias.

Para um schema com outro nome, crie-o e selecione-o na conexão; execute somente `01` a `04`. `install.sql`, `00`, `05` e `06` usam explicitamente o nome padrão. Em phpMyAdmin, observe os limites de upload/tempo do PHP; o catálogo é um arquivo SQL de aproximadamente 17 MB. Workbench ou cliente mysql são as opções recomendadas para a carga completa.

## Decisões de modelagem

| Assunto | Implementação |
| --- | --- |
| ID de carta | `cards.card_id` preserva códigos como `OP01-025`; não é renumerado |
| Arte/impressão | `printings.printing_id` é interno; `legacy_uid` preserva o UID usado pelo front |
| Cores e atributos | Relações N:N, permitindo cartas multicoloridas/múltiplos atributos |
| Textos | Leitura principal em `card_texts`, redações observadas em `printing_texts` |
| Valor ausente | SQL NULL; custo/poder zero continuam zero; original integral preservado |
| Fontes | Provedor, URL, carga e SHA-256, além de `raw_payload` com `fieldSources` |
| Imagens | URL, caminho relativo, MIME, tamanho e SHA-256; arquivos fora do banco |
| Produtos | Produtos físicos e grupos do catálogo distinguem-se em `catalog_groups.group_kind` |
| Características | `traits` / `printing_traits` preservam características impressas |
| Herói, Pirata, D. | `discovery_tags` identifica explicitamente as classificações editoriais |
| Personagens | N:N; dez candidatos iniciais ligados por nome exato normalizado, com revisão pendente |
| Decks | Uma linha por ID em `deck_items`; a arte escolhida não muda o limite de cópias |
| Arte incorreta | FKs compostas impedem associar impressão de outro ID a deck, coleção ou oferta |
| Coleção | Pode guardar posse por ID sem arte conhecida; escopo gerado impede duplicação com NULL |
| Integrações | IDs por provedor e cargas versionadas; nenhum token/chave externa incluído |
| Dinheiro | DECIMAL + código de moeda + instante da consulta; nenhum preço fictício inserido |
| Narrativa | Fonte e revisão por impressão; sem capítulos/episódios inventados |

Os 3.123 IDs cobrem o índice externo consultado em 23/10/2026. Não é garantia de todas as artes, idiomas ou cartas futuras no mundo. Há 117 IDs em prévia. Para alguns IDs complementares, a impressão física é japonesa, chinesa, coreana ou tailandesa, embora o texto consultado esteja em inglês. O idioma físico (`printings.language_code`) e o do texto (`printing_texts.language_code`) ficam separados. As grafias originais `jp`, `cn`, `tc` etc. permanecem em `source_language_code` e no JSON bruto.

Diferenças de texto, poder, Counter ou características entre versões não são descartadas: as colunas `printed_*`, `printing_texts`, `printing_traits` e `raw_payload` preservam a observação. A ficha canônica é uma conveniência de consulta, não uma errata oficialmente revisada. `printing_keyword_mentions` registra que a palavra aparece no texto, sem afirmar que a carta tenha a habilidade permanentemente.

Os grupos editoriais usam as mesmas regras do frontend v0.4; “Herói/antagonista” varia por arco e deve ser revisado. Os 358 vínculos iniciais de personagens são apenas correspondências de nomes dos dez personagens cadastrados, não uma enciclopédia completa. Nomes de cartas com várias pessoas não são separados automaticamente. Tabelas narrativas e ofertas permanecem vazias até existirem fontes.

## Decks, validação e coleção

`v_deck_issues` identifica falta/tipo de Líder, total diferente de 50, quantidade/tipo de DON!!, tipo inválido no principal, cópias acima do limite e cores incompatíveis. Exceções explícitas de quantidade reconhecidas na fonte estão em `cards.deck_copy_limit`, por exemplo Pacifista.

CHECKs e FKs protegem cada linha. Totais de várias linhas, escolha da categoria do Líder e cores exigem validação de conjunto. **O campo `status='ready'` não é um certificado automático do banco.** Na API futura, a operação de salvar/publicar deve:

1. Abrir transação e carregar o deck com `SELECT ... FOR UPDATE`, filtrando `user_id` da sessão e `deleted_at IS NULL`.
2. Conferir `revision`, atualizar nome/Líder/itens e incrementar a revisão.
3. Consultar `v_deck_issues` na mesma transação; recusar ready se houver problemas.
4. Conferir o regulamento competitivo vigente, idiomas, prévias e restrições quando o formato exigir.
5. Commit somente após todas as condições; rollback em qualquer falha.

Rascunhos incompletos são permitidos de propósito. O banco não cristaliza uma lista de banidas como verdade eterna. A revisão é estrutural e a legalidade competitiva depende do formato/data/evento. `v_deck_summary` e `v_deck_cost_curve` auxiliam a interface. Custos desconhecidos ficam fora da curva.

`v_collection_totals` soma a posse por ID. `v_deck_shopping_missing` calcula o que falta para **um deck de cada vez**; não reserva automaticamente cartas para vários decks simultâneos. Se o DON!! estiver genérico, soma recursos de qualquer arte; se um ID de DON!! estiver escolhido, considera aquele ID. Uma arte preferida no deck é preferência visual: a lista de compra considera cópias de qualquer arte do mesmo ID.

Quando uma posse genérica por ID for detalhada em artes, mova as quantidades em uma transação; não some os mesmos exemplares novamente. O backend deve validar idioma/condição contra a impressão quando informados. FKs garantem a identidade da carta, mas não afirmam que uma condição declarada pelo usuário é verdadeira.

## Conexão e integração PHP

Veja `examples/.env.example` e `examples/connection.php`. Configure as variáveis no ambiente do servidor; o exemplo não instala um leitor de `.env`. Nunca coloque credenciais dentro de `dist/`, JavaScript, GitHub ou no SQL público.

O exemplo `examples/CatalogRepository.php` demonstra consulta preparada com filtros e paginação por cursor. É um adaptador inicial para o backend, fora da pasta pública. Ele não cria uma rota de API, sessão ou autorização. PHP não estava instalado no ambiente desta entrega; os exemplos não foram executados aqui.

Crie um usuário MySQL próprio para a aplicação, com SELECT/INSERT/UPDATE/DELETE no schema e sem permissão de DDL. O usuário de migração é separado. Views usam `SQL SECURITY INVOKER`. Elas não implementam isolamento por usuário: a API deve sempre extrair o dono da sessão e filtrar `user_id`; nunca aceitar o dono apenas do corpo/URL enviado pelo navegador. UUID público ajuda na integração, mas não substitui autorização.

Use `password_hash`/`password_verify` no PHP, sessões/cookies seguros e proteção CSRF. Tokens de sessão/reset devem ser aleatórios e persistidos apenas como SHA-256 binário. Tabelas de tokens são uma opção para o backend futuro, não uma implementação pronta de autenticação. Sessões PHP nativas também podem ser usadas com o mecanismo próprio do servidor.

## Atualizações futuras

A carga entregue é inicial, não sincronização incremental de produção. `build-seed.mjs` recria arquivos SQL para um banco vazio; **não reaplique seu resultado em um banco com usuários/decks**. IDs numéricos da carga são convenientes para os relacionamentos iniciais e não são estáveis entre diferentes fotografias.

Em um importador de produção, localize `cards` por `card_id`, `printings` por `legacy_uid` e grupos por `catalog_key`. Depois utilize os IDs internos encontrados. Confirme IDs externos antes de gravar `external_printing_ids`; essa tabela inicia vazia porque o UID interno de uma arte nem sempre é o ID real da API externa. Atualize relações apenas do conjunto importado, dentro de transação. Não use REPLACE nem desative FKs. Preserve edições revisadas e archive registros retirados da fonte em vez de apagar cartas usadas em decks/coleções.

Use uma nova linha em `import_runs` para cada fotografia, valide o conteúdo antes do commit e trate colisões/erros em uma área de revisão. O JSON bruto não substitui tabelas relacionais de filtros. Não armazene credenciais em `raw_payload`, estatísticas ou auditoria. Processos de atualização de preços exigem fonte autorizada e política de expiração; o diretório de lojas não fornece estoque em tempo real.

`updated_at` de um deck não muda automaticamente quando apenas um item é alterado. O backend deve incrementar `revision`/atualizar o registro do deck na mesma transação. Atualizações com a revisão anterior evitam sobrescrever edições concorrentes.

## Reproduzir os arquivos

Na pasta do projeto completo, com Node.js 18+:

```text
node database/tools/build-schema.mjs
node database/tools/build-seed.mjs
```

O primeiro gera estrutura e dicionário; o segundo usa `dist/catalog-data.json`, manifesto, descrições, taxonomia e capítulos para gerar a carga, hashes e `install.sql`. Não acessa API nem MySQL. Precisa dos arquivos de imagem locais para calcular SHA-256. O ZIP só do banco já contém o SQL pronto, mas não todas as imagens do site.

Verificação via cliente mysql instalado e credenciais de desenvolvimento em variáveis de ambiente:

```text
node database/tools/verify.mjs
node database/tools/verify.mjs --write-tests
```

Variáveis: `MYSQL_BIN` (caminho do cliente mysql, opcional se estiver no PATH), `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`. O modo padrão é somente leitura e verifica a carga inicial. `--write-tests` também exige permissão de escrita: executa testes com transações e rollback, sem deixar usuários/decks de exemplo. Execute após uma instalação nova; contagens de seed podem mudar legitimamente após uso da aplicação. O relatório desta entrega está em `docs/VERIFICACAO.json`.

## Arquivos e documentação

- `install.sql`: instalação completa, independente de Node.js.
- `00` a `04`: etapas da mesma instalação; não executar novamente depois do instalador completo.
- `05_verify.sql`: conferência somente leitura.
- `06_example_queries.sql`: consultas de exploração e integração.
- `docs/DICIONARIO.md`: colunas, tipos, nulabilidade, restrições e índices das 51 tabelas.
- `docs/MER.md` e `docs/mer-completo.mmd`: diagrama principal e relacionamentos completos.
- `docs/INTEGRACOES.md`: contratos e migração do armazenamento atual do navegador.
- `docs/IMPORTACAO.json`: contagens, escopo e SHA-256 da fotografia.
- `docs/VERIFICACAO.json`: resultado dos testes no MySQL real.

Documentação de referência: [CHECK no MySQL 8.4](https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html), [chaves estrangeiras](https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html), [PDO](https://www.php.net/manual/en/book.pdo.php). Dados: [Bandai](https://en.onepiece-cardgame.com/cardlist/) e [OPlayTCG](https://oplaytcg.com/en/library). Imagens pertencem aos seus titulares; os créditos do frontend foram preservados.
