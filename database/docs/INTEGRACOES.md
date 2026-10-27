# Integrações futuras e contratos

Esta é uma proposta de integração, não uma API já implementada. O frontend continua independente do banco.

## Identificadores estáveis

| Origem atual | Campo no banco | Uso |
| --- | --- | --- |
| `card.id` | `cards.card_id` | Busca, favoritos, cópias, lista de compras |
| `card.uid` | `printings.legacy_uid` | Abrir arte específica e preservar links atuais |
| `card.sets[].id` | `catalog_groups.catalog_key` | Preservar filtros por produto/grupo |
| Número interno da impressão | `printings.printing_id` | FKs internas; consultar pelo UID, não assumir a sequência |
| `atlas-deck-draft.leader` | impressão por `legacy_uid` → `leader_card_id` e `leader_printing_id` | Importar a identidade e a escolha visual |
| `atlas-deck-draft.cards[ID]` | `deck_items.card_id`, quantidade e impressão preferida | Somar cópias por ID |
| `atlas-deck-draft.donArt` | impressão por `legacy_uid` → `don_card_id` e `don_printing_id` | Recurso separado do principal |
| `atlas-favorites[]` | `favorites(user_id,card_id)` | Lista de IDs distintos |
| `atlas-owned[ID]` | `collection_entries` com `printing_id=NULL`, idioma `und` e condição `unknown` | Não inventar versão física da posse antiga |
| `atlas-lessons[]` | `lesson_progress` por slug | Progresso vinculado ao usuário autenticado |

`public_id` de usuário/deck deve ser UUID gerado no servidor. BIGINTs internos devem ser serializados como string em JSON se houver risco de ultrapassar o inteiro seguro do JavaScript. Os códigos de carta continuam strings, inclusive DON!! e promos.

## Rotas sugeridas

| Rota futura | Responsabilidade |
| --- | --- |
| `GET /api/v1/cards` | Busca, cor, tipo, custo, atributos, características e grupos |
| `GET /api/v1/cards/{cardId}` | Identidade e textos, com versões disponíveis |
| `GET /api/v1/printings/{uid}` | Ficha e arte específicas, origem e explicação |
| `GET /api/v1/catalog-groups` | Produtos e agrupamentos separados |
| `GET /api/v1/tags` | Vocabulário, natureza impressa/editorial e descrição |
| `GET/POST /api/v1/me/decks` | Decks do dono autenticado |
| `PATCH /api/v1/me/decks/{publicId}` | Edição com `revision`, transação e validação |
| `GET /api/v1/me/decks/{publicId}/missing` | Falta por ID para aquele deck |
| `PUT /api/v1/me/collection` | Operações idempotentes de posse, sem somar reenvios |
| `PUT/DELETE /api/v1/me/favorites/{cardId}` | Favoritar/desfavoritar idempotente |

Filtros com SQL devem usar placeholders, não concatenar valores. Ordenações só podem usar uma lista de opções conhecidas. O exemplo PDO usa cursor `card_id`, ordem lexicográfica estável; para reproduzir a ordenação numérica visual do frontend, defina uma chave de ordenação própria em uma migração futura. Não misture cursor de uma ordem com consulta de outra.

A busca por nome usa o texto canônico inglês. Uma busca narrativa completa por personagem pode usar aliases e `card_characters`, mas essa relação inicial tem cobertura parcial. Consulta textual pelo nome continua disponível para todas as cartas. Para cartas por impressão/produto, aplique filtros em `v_catalog_printings`, `printing_groups` e `printing_traits` antes de agrupar IDs.

## Esboço de resposta

```json
{
  "data": [{
    "id": "OP01-025",
    "name": "Roronoa Zoro",
    "type": "CHARACTER",
    "colors": ["Red"],
    "cost": 3,
    "power": 5000,
    "counter": null,
    "language": "en",
    "isPreview": false
  }],
  "nextCursor": "OP01-025",
  "catalogSnapshot": "2026-10-23"
}
```

Exemplo de contrato, não uma resposta completa/real de um endpoint. Para o frontend atual, um adaptador deverá reconstruir `color` e `archetype` ou migrar a interface para arrays normalizados. O idioma do texto e o físico da impressão precisam continuar distintos. Preços são valores decimais com moeda, preferencialmente strings na API para evitar ponto flutuante.

## Importação do navegador

O conteúdo de localStorage é entrada não confiável, mesmo que venha do próprio site. Exija login real; extraia o usuário da sessão. Valide schema, tamanho do arquivo, IDs/UIDs, quantidades, cores e limites. Mostre um resumo antes de concluir a migração. Um arquivo de deck não pode atribuir-se a outro dono.

Para uma lista exportada `grand-line-atlas-deck-v1`, resolva líder/itens/arte de DON!! pelos UIDs existentes, não por nomes e não pelos IDs numéricos da carga. Some linhas com o mesmo `card_id`. Para posse antiga sem arte, grave a quantidade total por ID no escopo genérico. Repetir uma importação deve atualizar a quantidade escolhida, não duplicá-la; use uma chave de idempotência de aplicação ou um registro de migração por usuário. Para transferir posse genérica a impressões identificadas, debite e credite dentro de uma transação.

## Cargas externas

O SQL entregue não faz chamadas externas. Importe atualizações em uma área de validação, preserve a fonte e gere uma nova fotografia. Atualize registros por chave de negócio, com os IDs internos reais daquela instalação. Uma arte retirada da fonte deve ser arquivada até revisão, não apagada se estiver em decks ou coleções.

`external_card_ids` já registra correspondências de carta conhecidas. `external_printing_ids` começa vazia: um UID sintético de frontend não é prova do ID externo. Não reaproveite IDs de um provedor para outro sem a chave `source_id`. Crie adaptadores versionados, respeite limites e condições dos serviços e preserve alterações editoriais revisadas.

## Segurança e operação

- Não exponha MySQL diretamente ao navegador. A API PHP consulta o banco e retorna somente os campos necessários.
- Use usuário de aplicação sem DDL e usuário de migração separado; não use root no backend.
- Guarde credenciais no ambiente do servidor. `database/` e arquivos de backup ficam fora da pasta pública `dist/`.
- Não armazene senhas, tokens ou cookies em JSON bruto, auditoria ou logs. Use hash de senha apropriado e hashes de tokens aleatórios.
- FKs não substituem autorização: filtre sempre pelo dono em decks, coleção, progresso e favoritos.
- Faça backup do banco e dos assets. Datas são UTC; converta para o fuso do usuário na apresentação.
- Defina políticas de retenção, exportação e exclusão de dados pessoais quando implementar usuários reais. `deleted_at` é um recurso de modelagem, não uma política completa de privacidade.
