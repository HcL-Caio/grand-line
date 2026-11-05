# Backend PHP · CRUD de decks

Primeira etapa do backend prevista no README do projeto: PHP + PDO + MySQL 8.4, autenticação por sessão, CRUD, filtros e listagem em PDF. Sem Composer e sem dependências: o `dist/` continua sendo servido como antes e só `/app/*` é tratado por PHP.

## O que existe

| Rota | Ação |
| --- | --- |
| `/app/cadastro`, `/app/login`, `/app/logout` | Conta, sessão PHP, CSRF |
| `/app/decks` | **Listagem** com 5 filtros (nome, formato, situação, visibilidade, cor do Líder), ordenação e paginação |
| `/app/decks.pdf` | **PDF** da listagem, respeitando os mesmos filtros |
| `/app/decks/novo` | **Criar** |
| `/app/decks/{id}` | **Ler**: Líder, totais, curva de custo, pendências, cartas |
| `/app/decks/{id}/editar` | **Atualizar**: dados, Líder, quantidades e busca de cartas (nome/ID, tipo, cor, custo, compatível com o Líder) |
| `/app/decks/{id}/excluir` | **Excluir** (lógico, `deleted_at`) |
| `/app/decks/{id}.pdf` | Lista de cartas de um deck |

Tabelas usadas: `users`, `decks`, `deck_items`, `audit_events` (escrita) e `cards`, `card_texts`, `card_colors`, `printings`, `printing_assets`, `play_formats` (leitura), além das views `v_deck_summary`, `v_deck_issues`, `v_deck_cost_curve`, `v_catalog_cards`. Nenhuma alteração no schema.

## Executar

1. Instale o banco (`database/README.md`) e crie um usuário MySQL da aplicação com SELECT/INSERT/UPDATE/DELETE (sem DDL).
2. Configure o ambiente (nunca em arquivo versionado):

```text
export DB_USER=atlas_app DB_PASSWORD=sua_senha   # opcionais: DB_HOST, DB_PORT, DB_NAME
php -S localhost:8000 -t dist backend/router.php
```

Abra **http://localhost:8000/app**. Requer PHP 8.1+ com `pdo_mysql`, `mbstring` e `intl` (esta é opcional: só melhora a medida de acentos no PDF).

## Decisões

- **Dono sempre vem da sessão.** Toda consulta filtra `user_id` e `deleted_at IS NULL`; deck de outro usuário responde 404. URLs usam `public_id` (UUID).
- **Concorrência:** cada escrita trava a linha (`SELECT … FOR UPDATE`), confere `revision`, e incrementa. Aba desatualizada recebe aviso em vez de sobrescrever.
- **Rascunho pode ser incompleto**, mas "Pronto" só passa com `v_deck_issues` vazio na mesma transação. Se uma alteração deixar um deck pronto com pendências, ele volta a Rascunho com aviso. Isso é verificação estrutural, não legalidade competitiva.
- **Regras duras ao adicionar:** carta existente, tipo do deck principal, limite por ID (`deck_copy_limit`) e no máximo 50. Cores ficam para as pendências.
- **Segurança:** `password_hash` (Argon2id, ou bcrypt com limite de 72 bytes quando indisponível), `session_regenerate_id` no login, cookie HttpOnly/SameSite, CSRF em todo POST, bloqueio após 5 falhas em 15 min (via `audit_events`, sem guardar o e-mail), saída escapada, redirecionamentos só internos, CSP e demais cabeçalhos, erros detalhados só no log.
- **Fora do escopo desta etapa:** recuperação de senha por e-mail, verificação de e-mail, coleção/favoritos no servidor, perfis `editor`/`admin`, escolha de arte (`preferred_printing_id`) e DON!! específico.

## Testes

```text
php backend/tests/run.php          # repositórios, autenticação, filtros, PDF (SQLite com o catálogo real)
php backend/tests/http.php         # ponta a ponta com servidor, cookies e CSRF reais
php backend/tests/run.php --mysql  # a mesma suíte no SEU MySQL 8.4 (DB_*), dentro de uma transação com ROLLBACK
```

Os dois primeiros usam SQLite com as 3.123 cartas reais e as **views reais** de `database/04_views.sql` (com ajustes mínimos de sintaxe). Eles **não substituem o MySQL**: collation, `FOR UPDATE` e *prepared statements* nativos só são exercitados com `--mysql`. Rode essa opção antes de entregar.
