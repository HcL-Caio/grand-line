# Grand Line Atlas · Frontend e banco MySQL v0.5

Guia acadêmico de One Piece Card Game, em português, com HTML, CSS, JavaScript e Bootstrap 5.3.3. A apresentação original foi mantida e conectada às páginas internas.

## Banco MySQL disponível

A pasta [database](database/README.md) contém estrutura MySQL 8.4, instalação SQL completa e carga de **3.123 IDs / 5.343 impressões**, imagens referenciadas, textos originais e explicações em português. São **51 tabelas e nove views**, com relações de filtros, usuários, decks, coleção, aprendizagem, narrativa e futuras integrações. Há MER, dicionário completo, consultas de exemplo e adaptador inicial PDO. **75 verificações passaram no MySQL 8.4.10**, incluindo preservação integral dos 5.343 registros JSON e testes de FKs/CHECKs com rollback. O frontend ainda usa dados estáticos; o backend PHP deve ser conectado em uma etapa posterior.

## Páginas

| Arquivo em `dist/` | Conteúdo |
| --- | --- |
| `index.html` | Apresentação com capas e cartas em destaque |
| `login.html` | Login, exibição de senha e acesso como visitante |
| `cadastro.html` | Cadastro com confirmação e indicador de força da senha |
| `recuperar.html` | Interface de recuperação de acesso |
| `home.html` | Home com catálogo, filtros, ordenação e paginação |
| `favoritos.html` | Cartas favoritas salvas no navegador |
| `carta.html?id=OP01-025` | Ficha da carta, produtos e versões |
| `aprender.html` | Academia Pirata com 14 capítulos, exercícios e quiz |
| `decks.html` | Construtor guiado, revisão, curva de custo e mão inicial |
| `comprar.html` | Formas de aquisição, lojas e lista de cartas faltantes |

## Executar

Não há dependências de produção para instalar nem compilação.

Com Node.js 18 ou superior, execute na pasta do projeto:

```text
node server.mjs
```

Abra **http://127.0.0.1:4173** e mantenha o terminal aberto. Ctrl+C encerra o servidor. Para mudar a porta, defina a variável de ambiente `PORT`. O servidor só fornece arquivos estáticos; não implementa autenticação.

Com PHP instalado:

```text
php -S localhost:8000 -t dist
```

Abra **http://localhost:8000**. Esse comando apenas serve o frontend; o backend ainda será implementado.

Também é possível abrir `dist/index.html` diretamente ou usar o Live Server do VS Code. Prefira HTTP para armazenamento consistente de favoritos e progresso entre páginas. O comportamento de armazenamento em `file://` varia entre navegadores.

As 5.343 ilustrações do catálogo estão incluídas localmente: 5.329 arquivos WebP otimizados e 14 PNGs já existentes. As capas e o Bootstrap também estão incluídos. Não é necessário acessar a Bandai para exibir as fotos desta fotografia do catálogo. A interface tenta a imagem da fonte se um arquivo local estiver ausente e mostra uma mensagem se ambas falharem. Os links para fontes, erratas e arquivos originais exigem internet. As fontes Google são opcionais, com alternativas locais.

## Acabamento visual e descrições

A versão 0.3 acrescenta galeria de seis cartas em destaque, mapa marítimo vetorial, reflexos nas cartas, inclinação sutil com ponteiro, entradas na rolagem, detalhes de luz e movimento das capas. As fichas possuem pôster de coleção e ampliação da arte em modal. O botão no canto inferior direito permite pausar as animações; a preferência de movimento reduzido do sistema é respeitada. Nenhuma informação depende de animação ou de passar o mouse.

Todas as 5.343 impressões possuem resumo em português dos campos publicados, explicação das janelas de ativação, características e habilidades mencionadas. Há 24 leituras editoriais específicas por ID, aplicadas às respectivas versões. Essas explicações não são traduções integrais certificadas e não substituem o texto oficial em inglês. Habilidades concedidas sob condição não são apresentadas como habilidades permanentes do Personagem. Capítulos, episódios e origem da arte permanecem em pesquisa, sem referências inventadas.

## Catálogo

Fotografia consultada em **23/10/2026**, unindo o catálogo inglês da Bandai (NA/EU/OC/LATAM/ME) e complementos do OPlayTCG:

- **3.123 IDs distintos e 5.343 versões de impressão**, todos com foto local.
- Base preservada: 2.850 IDs e 4.910 impressões da Bandai.
- Complemento: **273 IDs e 433 impressões** que faltavam, incluindo 167 IDs de DON!!, promos e prévias.
- Os 3.123 IDs retornados pelo índice do OPlayTCG estão cobertos: nenhum ID desse índice ficou ausente.
- **65 produtos / grupos**: 61 produtos do importador oficial e quatro agrupamentos complementares. Nem todo grupo corresponde a um produto físico.
- **117 IDs em prévia** no conjunto completo. Prévia não comprova lançamento, estoque ou elegibilidade em evento.
- **312 campos numéricos** antes ausentes foram complementados com valores explícitos do índice do OPlayTCG, incluindo custo e poder zero. A origem fica em `fieldSources`.

O escopo inclui as impressões inglesas disponíveis para os IDs complementares e, quando não há versão inglesa na fonte, impressões de outro idioma com o idioma preservado nos dados. Não representa todas as artes ou idiomas existentes no mundo nem cartas futuras ainda não divulgadas. A origem de cada impressão e a marcação de ilustração provisória aparecem na ficha.

O catálogo inicia com uma carta por ID. “Mostrar todas as versões” expande as impressões. Os filtros são aplicados antes do agrupamento para preservar resultados de produtos específicos.

Filtros: personagem, grupos narrativos e impressos com combinação “todos” ou “qualquer”, prévias e ilustração provisória; busca por nome, ID, característica e texto; cor e multicolor; tipo; raridade; produto; atributo; Counter; habilidade ou ativação; característica impressa; bloco; intervalos de custo e poder. Há chips para remover filtros, limpeza geral, ordenação por ID/nome/custo/poder e páginas de 24, 48 ou 96 itens.

Campos sem valor numérico nas fontes ficam fora dos filtros numéricos. Líderes possuem vida inicial em vez de custo. O frontend conserva os valores publicados e só complementa campos com dados explícitos da fonte secundária, sem deduzir números pela ilustração. “Heróis”, “Antagonistas”, “Piratas” e família “D.” são classificações editoriais sinalizadas, não características oficiais do TCG; os papéis narrativos podem variar por arco. Chapéus de Palha, Marinha e outros grupos impressos usam as características das cartas.

As fichas preservam o efeito em inglês, mostram produtos associados e permitem alternar artes. Favoritos são vinculados ao ID da carta, abrangendo suas versões. Os dados ficam em `dist/catalog-data.js` e `dist/catalog-data.json`; esta fotografia não depende de API para consultas.

Para atualizar a fotografia:

```text
node tools/import-catalog.mjs --refresh
node tools/extend-catalog.mjs --refresh
node tools/download-card-images.mjs
```

O importador mantém cache intermediário em `../../work/catalog-cache` a partir desta pasta. Sem `--refresh`, reutiliza o cache. Falhas de produto interrompem a atualização e preservam os dados anteriores. Ele não baixa imagens nem altera banco de dados. O complemento mantém cache em `../../work/oplay-card-cache`, preserva uma cópia da base oficial e respeita respostas de limite de requisições. Depois de importar a base oficial, execute o complemento antes das imagens para manter a cobertura combinada. Atualizar exige conexão; a consulta ao site pronto é local.

## Estaleiro de decks

O construtor guia três etapas: escolher Líder, adicionar cartas e revisar. Ele considera as cores do Líder, 50 cartas no deck principal, dez DON!! e quatro cópias por ID somando artes diferentes, com exceções explícitas reconhecidas no texto de cartas como Pacifista. É possível mudar o Líder; cartas que ficarem incompatíveis são marcadas para correção.

Há busca, filtros por grupo e tipo, seleção de prévias, curva de custo, contagens de tipos e Counter, escolha da arte de DON!!, mão ilustrativa de cinco cartas e uma troca completa da mão. A simulação reembaralha as 50 cartas e não altera o deck.

Rascunho e listas nomeadas ficam em localStorage. O usuário pode exportar JSON para reimportação no Atlas ou texto para consulta. Arquivos JSON são validados antes de substituir o rascunho. O formato JSON é próprio do projeto, sem promessa de compatibilidade com outros construtores.

A revisão de estudo verifica a estrutura básica. A opção Standard acrescenta verificações da fotografia de banidas e pares proibidos consultada em 23/10/2026, vigente desde 12/10/2026, e avisa sobre blocos. Isso **não certifica legalidade competitiva**: as listas e elegibilidades por ID podem mudar. Os dados dessa revisão estão em `deck-core.js` e exigem atualização com as fontes oficiais.

## Onde conseguir e minha coleção

A página explica starter decks, cartas avulsas, selados e promoções. A busca por ID conecta a impressão aos produtos ou grupos registrados e à fonte. O diretório inclui quatro lojas/marketplaces brasileiros e um internacional, com filtro de região. São links de consulta, sem preço, estoque ou integração comercial sincronizada.

A lista de compras usa o rascunho ou um deck salvo, soma Líder, deck principal e dez DON!!, permite registrar quantidades possuídas por ID e calcula o restante. As quantidades ficam no navegador e são compartilhadas entre listas. É possível copiar só o que falta. Links em cada ficha levam ao construtor e à consulta de aquisição.

## Academia Pirata

1. Objetivo e condições de vitória.
2. Líder, Personagem, Evento, Cenário e DON!!.
3. Anatomia interativa de uma carta.
4. Cores e tendências estratégicas.
5. Deck, cores, limite por ID e versões.
6. Mapa do campo, zonas e estados.
7. Preparação, mão inicial, mulligan e vida.
8. Fases do turno e exceções de primeiro e segundo jogador.
9. DON!!: pagar, atribuir e devolver.
10. Combate, Counter e simulador de comparação de poder.
11. Palavras-chave e marcadores de ativação.
12. Efeitos e dúvidas frequentes.
13. Formatos, blocos, erratas, versões e fontes oficiais.
14. Quiz de 12 perguntas com correção comentada.

O progresso fica no navegador. O simulador é um exercício de comparação de poder; não simula partidas completas, mãos ou interações de cartas. As descrições de cores são tendências gerais. Cada capítulo aponta fontes. A trilha ensina fundamentos e interações importantes; casos específicos exigem consulta às regras completas e FAQ.

## Autenticação e limites

Login, cadastro e recuperação são **interfaces de frontend**. Nenhum usuário é criado ou autenticado, nenhuma senha é salva e nenhum e-mail é enviado. Após validar o formulário, a interface informa esse estado e oferece acesso à home demonstrativa. Não há sessão fictícia ou proteção de rotas no navegador.

As relações entre ilustração, capítulo, episódio e contexto narrativo continuam como **pesquisa pendente**, visível nas fichas. Não foram inventadas referências. Algumas artes são originais. Um produto listado não garante que cada pacote contenha a carta.

O banco MySQL e a carga inicial estão prontos em `database/`. Backend PHP, autenticação real, CRUDs administrativos e relatório PDF continuam como a próxima etapa acadêmica.

## Organização

- `styles.css` / `app.js`: apresentação original.
- `pages.css`: páginas internas, autenticação e responsividade.
- `shared.js`: navegação, utilitários e favoritos.
- `taxonomy.js`: personagens, grupos e classificações editoriais.
- `deck-core.js` / `decks.js`: regras de estrutura e interface do construtor.
- `comprar.js`: aquisição, diretório e quantidades possuídas.
- `crew-pages.css`: filtros de grupos, estaleiro e página de aquisição.
- `tools/extend-catalog.mjs`: cobertura complementar do OPlayTCG.
- `catalog.js`: filtros, ordenação, agrupamento e paginação.
- `card-page.js`: ficha e versões.
- `auth.js`: validação dos formulários.
- `learn-content.js`: capítulos e questões.
- `learn.js`: navegação didática, progresso e exercícios.
- `assets/`: Bootstrap, capas e imagens locais.
- `tools/import-catalog.mjs`: atualização dos dados.
- `card-descriptions.js`: resumos e leituras editoriais.
- `image-manifest.js`: relação entre impressão e imagem local.
- `voyage.css` / `voyage.js`: acabamento visual, animações e controles de movimento.
- `spotlight-data.js` / `spotlight.js`: seleção e galeria de destaques da apresentação.
- `assets/sea-chart.svg`: mapa vetorial decorativo original do projeto.
- `assets/cards/`: ilustrações otimizadas em WebP.
- `tools/download-card-images.mjs`: download retomável e otimização de imagens.
- `server.mjs`: servidor estático opcional, sem dependências.

Os arquivos da interface listados acima estão em `dist/`.

## Atualizar imagens

As imagens prontas já acompanham a entrega. Para baixar novamente ou incluir novas cartas importadas, o script usa Node.js e a biblioteca de processamento sharp:

```text
npm install --no-save sharp
node tools/download-card-images.mjs
```

Ele reutiliza arquivos concluídos, verifica se a origem é uma imagem decodificável, preserva marcas da fonte oficial e gera `dist/image-manifest.js`. Não redesenha nem remove marcas das cartas. A fotografia atual ocupa aproximadamente 580 MiB de arquivos de cartas. Para publicar no GitHub, considere o volume dos assets e a hospedagem escolhida.

## Verificação

Foram concluídas 50 verificações de catálogo, taxonomia, regras de decks, importação/exportação, descrições e referências HTML; as 60 verificações anteriores de DOM continuam passando. Outras 36 verificações de integração exercitam os novos filtros, construção e salvamento de decks, mão inicial, lista de compras, posse de cartas, busca e navegação. Os 5.343 vínculos de imagem local, tamanhos e assinaturas de arquivo foram verificados, assim como a cobertura de resumos, 24 leituras editoriais e a sintaxe dos scripts. As dez páginas têm referências locais válidas.

Testes de DOM não verificam aparência ou medidas. A revisão visual automatizada ficou pendente: o controle de navegador bloqueou o protocolo local `file://`. A publicação privada não foi concluída porque a revisão automática rejeitou o envio da credencial ao processo de publicação. Os arquivos locais são a entrega executável.

## Continuidade em PHP e MySQL

Use PDO com consultas preparadas, sessões reais, `password_hash`/`password_verify`, autorização no servidor e proteção CSRF. Use a estrutura e os exemplos de `database/` para substituir os dados estáticos por consultas paginadas. Separe carta (ID de regra) de impressão (arte, produto, raridade, idioma e bloco publicado). Vincule referências narrativas à impressão.

O enunciado exige Bootstrap, CRUD em PHP, MySQL, autenticação por sessão, pelo menos três filtros, uma listagem PDF e documentação com histórias de usuário, protótipo, MER e dicionário. Há quatro tabelas na introdução e seis tipos nos critérios: planeje pelo menos seis entidades relevantes, como usuários, cartas, produtos, personagens, impressões e referências narrativas, com relacionamentos adicionais conforme o MER.

O repositório é [HcL-Caio/grand-line-atlas](https://github.com/HcL-Caio/grand-line-atlas), inicialmente privado. Ele inclui o código do site, imagens, banco SQL e documentação. GitHub Pages pode servir a demonstração estática; PHP/MySQL exigem ambiente próprio. Credenciais, `.env`, configurações locais e ZIPs não são versionados.

Para enviar alterações futuras, execute nesta pasta:

```text
git add .
git commit -m "Descreva a alteração"
git push
```

Para dar acesso ao professor, adicione o usuário dele em Settings → Collaborators no GitHub. Como o repositório é privado, só quem tiver acesso poderá abrir seu conteúdo.

## Fontes e créditos

- Catálogo e imagens: https://en.onepiece-cardgame.com/cardlist/
- Regras e atualizações: https://en.onepiece-cardgame.com/rules/
- Manual: https://en.onepiece-cardgame.com/pdf/rule_manual.pdf
- Regras completas: https://en.onepiece-cardgame.com/pdf/rule_comprehensive.pdf
- Resumo em português: https://en.onepiece-cardgame.com/pdf/rule_overview_sheet_pt.pdf
- Guia de jogo: https://en.onepiece-cardgame.com/play-guide/
- FAQ: https://en.onepiece-cardgame.com/rules/faq/
- Blocos e formatos: https://en.onepiece-cardgame.com/topics/013.php
- Recursos de ensino: https://en.onepiece-cardgame.com/beginners/how-to-play.php
- Catálogo complementar, prévias e imagens adicionais: https://oplaytcg.com/en/library
- Banidas, restrições e pares proibidos: https://en.onepiece-cardgame.com/news/restriction.html
- Produtos: https://en.onepiece-cardgame.com/products/
- Busca de eventos: https://www.bandai-tcg-plus.com/
- Capas: https://sp.shonenjump.com/j/rensai/_list/onepiece/
- Bootstrap: https://getbootstrap.com/ — licença MIT preservada no CSS.

Projeto de fã, independente e acadêmico. One Piece © Eiichiro Oda / Shueisha / Toei Animation; Card Game © Bandai. Imagens e marcas pertencem aos seus titulares.
