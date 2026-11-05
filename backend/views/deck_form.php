<?php
use Atlas\Cards;
use Atlas\Decks;

/**
 * @var string $mode create|edit  @var ?array $deck  @var list<string> $errors  @var array<string,mixed> $old
 * @var list<array> $formats  @var list<array> $leaders
 * Somente no modo edit: @var list<array> $items @var array<string,int> $totals @var list<array> $issues @var array<string,string> $search @var ?array $found
 */
$editing = $mode === 'edit';
$base = $editing ? url('/decks/' . $deck['public_id']) : url('/decks');
$revision = $editing ? (int)$deck['revision'] : 0;
?>
<div class="workspace-intro">
  <div>
    <div class="eyebrow gold"><a href="<?= e($editing ? $base : url('/decks')) ?>" class="cx-crumb">← <?= $editing ? 'VOLTAR AO DECK' : 'MEUS DECKS' ?></a></div>
    <h1><?= $editing ? 'Editar deck' : 'Novo deck' ?></h1>
    <p><?= $editing ? 'Ajuste os dados, escolha o Líder e monte a lista. Cada alteração é salva ao enviar o formulário.' : 'Comece pelo básico. Você escolhe as cartas na próxima tela.' ?></p>
  </div>
</div>

<?php if ($errors !== []): ?>
  <div class="cx-flash cx-flash-error" role="alert" tabindex="-1">
    <b>Não foi possível salvar:</b>
    <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<section class="cx-panel" aria-labelledby="meta-h">
  <h2 id="meta-h" class="cx-panel-title"><?= $editing ? 'Dados do deck' : 'Informações básicas' ?></h2>
  <form method="post" action="<?= e($editing ? $base . '/editar' : url('/decks')) ?>" class="cx-form">
    <?= csrfField() ?>
    <?php if ($editing): ?><input type="hidden" name="revision" value="<?= $revision ?>"><?php endif; ?>
    <label class="cx-field cx-field-wide">Nome do deck
      <input type="text" name="name" value="<?= e($old['name']) ?>" maxlength="100" required placeholder="Ex.: Chapéus de Palha Vermelho">
    </label>
    <label class="cx-field">Formato
      <select name="format_code">
        <?php foreach ($formats as $format): ?><option value="<?= e($format['format_code']) ?>"<?= $old['format_code'] === $format['format_code'] ? ' selected' : '' ?>><?= e($format['name_pt']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label class="cx-field">Visibilidade
      <select name="visibility">
        <?php foreach (Decks::VISIBILITIES as $code => $label): ?><option value="<?= e($code) ?>"<?= $old['visibility'] === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </label>
    <?php if ($editing): ?>
      <label class="cx-field">Situação
        <select name="status">
          <?php foreach (Decks::STATUSES as $code => $label): ?><option value="<?= e($code) ?>"<?= $old['status'] === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <small>“Pronto” só é aceito com a estrutura completa.</small>
      </label>
    <?php else: ?>
      <input type="hidden" name="status" value="draft">
      <label class="cx-field cx-field-wide">Líder (opcional agora)
        <select name="leader_card_id">
          <option value="">Escolher depois</option>
          <?php foreach ($leaders as $leader): ?>
            <option value="<?= e($leader['card_id']) ?>"<?= (string)$old['leader_card_id'] === $leader['card_id'] ? ' selected' : '' ?>><?= e($leader['card_id'] . ' — ' . $leader['name'] . ($leader['colors'] ? ' (' . implode('/', array_map('colorLabel', $leader['colors'])) . ')' : '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    <?php endif; ?>
    <label class="cx-field">DON!! (0 a 10)
      <input type="number" name="don_quantity" value="<?= e($old['don_quantity']) ?>" min="0" max="10" required>
    </label>
    <label class="cx-field cx-field-full">Anotações
      <textarea name="description" rows="3" maxlength="2000" placeholder="Estratégia, trocas planejadas, observações…"><?= e($old['description']) ?></textarea>
    </label>
    <div class="cx-form-actions">
      <button type="submit" class="cx-btn cx-btn-gold"><?= $editing ? 'Salvar dados' : 'Criar deck e escolher cartas' ?></button>
      <a class="cx-btn cx-btn-ghost" href="<?= e($editing ? $base : url('/decks')) ?>">Cancelar</a>
    </div>
  </form>
</section>

<?php if ($editing):
    $messages = array_values(array_unique(array_column($issues, 'message')));
    $back = '?' . http_build_query(array_filter(['search' => '1', 'q' => $search['q'], 'type' => $search['type'], 'color' => $search['color'], 'max_cost' => $search['max_cost'], 'compat' => $search['compat']], static fn ($v) => $v !== ''));
?>
<section class="cx-panel" aria-labelledby="leader-h">
  <h2 id="leader-h" class="cx-panel-title">Líder</h2>
  <p class="cx-muted">Atual: <b><?= $deck['leader_card_id'] === null ? 'nenhum' : e($deck['leader_card_id'] . ' — ' . $deck['leader_name']) ?></b>. Trocar o Líder mantém as cartas; as incompatíveis aparecem nas pendências.</p>
  <form method="post" action="<?= e($base . '/lider') ?>" class="cx-form cx-inline">
    <?= csrfField() ?><input type="hidden" name="revision" value="<?= $revision ?>">
    <label class="cx-field cx-field-wide">Escolher Líder
      <select name="card_id" required>
        <option value="">Selecione…</option>
        <?php foreach ($leaders as $leader): ?>
          <option value="<?= e($leader['card_id']) ?>"<?= $deck['leader_card_id'] === $leader['card_id'] ? ' selected' : '' ?>><?= e($leader['card_id'] . ' — ' . $leader['name'] . ($leader['colors'] ? ' (' . implode('/', array_map('colorLabel', $leader['colors'])) . ')' : '')) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="cx-form-actions"><button type="submit" class="cx-btn cx-btn-outline">Definir Líder</button></div>
  </form>
</section>

<section class="cx-panel" aria-labelledby="items-h">
  <h2 id="items-h" class="cx-panel-title">Cartas no deck <span class="cx-count-pill"><?= e($totals['main_quantity']) ?>/<?= Decks::MAIN_SIZE ?></span></h2>
  <?php if ($messages !== []): ?>
    <div class="cx-issues-box"><b>Pendências</b><ul class="cx-issues"><?php foreach ($messages as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div>
  <?php else: ?>
    <p class="cx-ok">✓ Estrutura básica válida.</p>
  <?php endif; ?>
  <?php if ($items === []): ?>
    <p class="cx-muted">Nenhuma carta ainda. Use a busca abaixo para adicionar.</p>
  <?php else: ?>
    <form method="post" action="<?= e($base . '/itens/atualizar') ?>">
      <?= csrfField() ?><input type="hidden" name="revision" value="<?= $revision ?>">
      <div class="cx-table-wrap">
        <table class="cx-table">
          <thead><tr><th scope="col">Carta</th><th scope="col">Tipo</th><th scope="col" class="num">Custo</th><th scope="col">Cor</th><th scope="col" class="num">Quantidade</th></tr></thead>
          <tbody>
          <?php foreach ($items as $item): $thumb = cardImage($item['image_path']); ?>
            <tr>
              <td><div class="cx-cardcell"><?php if ($thumb): ?><img src="<?= e($thumb) ?>" alt="" width="36" height="50" loading="lazy"><?php endif; ?><div><b><?= e($item['name']) ?></b><small><?= e($item['card_id']) ?></small></div></div></td>
              <td><?= e(typeLabel((string)$item['type_code'])) ?></td>
              <td class="num"><?= $item['cost'] === null ? '—' : e($item['cost']) ?></td>
              <td><?php foreach ($item['colors'] as $color): ?><span class="cx-dot" style="background:<?= e(colorHex($color)) ?>" title="<?= e(colorLabel($color)) ?>"></span><?php endforeach; ?></td>
              <td class="num"><input class="cx-qty" type="number" name="quantities[<?= e($item['card_id']) ?>]" value="<?= e($item['quantity']) ?>" min="0" max="<?= e(min(50, (int)$item['deck_copy_limit'])) ?>" aria-label="Quantidade de <?= e($item['name']) ?>"></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="cx-form-actions"><button type="submit" class="cx-btn cx-btn-gold">Salvar quantidades</button><small class="cx-muted">Use 0 para remover uma carta.</small></div>
    </form>
  <?php endif; ?>
</section>

<section class="cx-panel" aria-labelledby="search-h" id="busca">
  <h2 id="search-h" class="cx-panel-title">Adicionar cartas</h2>
  <form method="get" action="<?= e($base . '/editar') ?>#busca" class="cx-form cx-filters" role="search">
    <input type="hidden" name="search" value="1">
    <label class="cx-field cx-field-wide">Nome ou ID
      <input type="search" name="q" value="<?= e($search['q']) ?>" maxlength="100" placeholder="Ex.: Zoro, Nami, OP01-025">
    </label>
    <label class="cx-field">Tipo
      <select name="type"><option value="">Todos</option><?php foreach (Cards::SEARCH_TYPES as $type): ?><option value="<?= e($type) ?>"<?= $search['type'] === $type ? ' selected' : '' ?>><?= e(typeLabel($type)) ?></option><?php endforeach; ?></select>
    </label>
    <label class="cx-field">Cor
      <select name="color"><option value="">Todas</option><?php foreach (Cards::COLORS as $color): ?><option value="<?= e($color) ?>"<?= $search['color'] === $color ? ' selected' : '' ?>><?= e(colorLabel($color)) ?></option><?php endforeach; ?></select>
    </label>
    <label class="cx-field">Custo máximo
      <input type="number" name="max_cost" value="<?= e($search['max_cost']) ?>" min="0" max="99" placeholder="Qualquer">
    </label>
    <?php if ($deck['leader_card_id'] !== null): ?>
      <label class="cx-check"><input type="checkbox" name="compat" value="1"<?= $search['compat'] === '1' ? ' checked' : '' ?>> Só cartas compatíveis com o Líder</label>
    <?php endif; ?>
    <div class="cx-filter-actions"><button type="submit" class="cx-btn cx-btn-gold">Buscar</button></div>
  </form>

  <?php if ($found !== null): ?>
    <p class="cx-count" aria-live="polite"><?= e($found['total']) ?> carta<?= $found['total'] === 1 ? '' : 's' ?> encontrada<?= $found['total'] === 1 ? '' : 's' ?></p>
    <?php if ($found['rows'] === []): ?>
      <p class="cx-muted">Nada encontrado. Tente outro termo ou remova algum filtro.</p>
    <?php else: ?>
      <div class="cx-card-grid">
        <?php foreach ($found['rows'] as $card): $thumb = cardImage($card['image_path']); $isLeader = $card['type_code'] === 'LEADER'; $inMain = in_array($card['type_code'], Cards::MAIN_TYPES, true); ?>
          <article class="cx-card">
            <?php if ($thumb): ?><img src="<?= e($thumb) ?>" alt="<?= e($card['name']) ?> — <?= e($card['card_id']) ?>" width="160" height="224" loading="lazy"><?php else: ?><div class="cx-art-empty">Sem imagem</div><?php endif; ?>
            <div class="cx-card-body">
              <b><?= e($card['name']) ?></b>
              <small><?= e($card['card_id']) ?> · <?= e(typeLabel((string)$card['type_code'])) ?><?= $card['cost'] !== null ? ' · custo ' . e($card['cost']) : '' ?></small>
              <span><?php foreach ($card['colors'] as $color): ?><span class="cx-dot" style="background:<?= e(colorHex($color)) ?>" title="<?= e(colorLabel($color)) ?>"></span><?php endforeach; ?></span>
              <?php if ($isLeader): ?>
                <form method="post" action="<?= e($base . '/lider') ?>">
                  <?= csrfField() ?><input type="hidden" name="revision" value="<?= $revision ?>"><input type="hidden" name="card_id" value="<?= e($card['card_id']) ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
                  <button type="submit" class="cx-btn cx-btn-outline cx-btn-block">Definir como Líder</button>
                </form>
              <?php elseif ($inMain): ?>
                <form method="post" action="<?= e($base . '/itens') ?>" class="cx-add">
                  <?= csrfField() ?><input type="hidden" name="revision" value="<?= $revision ?>"><input type="hidden" name="card_id" value="<?= e($card['card_id']) ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
                  <input class="cx-qty" type="number" name="quantity" value="1" min="1" max="<?= e(min(50, (int)$card['deck_copy_limit'])) ?>" aria-label="Quantidade de <?= e($card['name']) ?>">
                  <button type="submit" class="cx-btn cx-btn-gold">Adicionar</button>
                </form>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <?php if ($found['pages'] > 1):
          $pageLink = static fn (int $page): string => $base . '/editar?' . http_build_query(array_filter(['search' => '1', 'q' => $search['q'], 'type' => $search['type'], 'color' => $search['color'], 'max_cost' => $search['max_cost'], 'compat' => $search['compat'], 'page' => $page > 1 ? $page : ''], static fn ($v) => $v !== '')) . '#busca';
      ?>
        <nav class="cx-pager" aria-label="Paginação da busca">
          <?php if ($found['page'] > 1): ?><a class="cx-btn cx-btn-ghost" href="<?= e($pageLink($found['page'] - 1)) ?>">← Anterior</a><?php endif; ?>
          <span>Página <?= e($found['page']) ?> de <?= e($found['pages']) ?></span>
          <?php if ($found['page'] < $found['pages']): ?><a class="cx-btn cx-btn-ghost" href="<?= e($pageLink($found['page'] + 1)) ?>">Próxima →</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  <?php else: ?>
    <p class="cx-muted">Busque por nome, ID, tipo, cor ou custo para adicionar cartas ao deck.</p>
  <?php endif; ?>
</section>
<?php endif; ?>
