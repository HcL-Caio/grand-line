<?php
use Atlas\Cards;
use Atlas\Decks;

/** @var array<string,string> $filters @var array{rows:list<array>,total:int,page:int,pages:int} $result @var list<array> $formats */
$hasFilter = array_filter(array_diff_key($filters, ['sort' => 1])) !== [];
$pageUrl = static fn (int $page): string => url('/decks', array_merge($filters, ['page' => $page > 1 ? $page : '']));
?>
<div class="workspace-intro">
  <div>
    <div class="eyebrow gold">SEU ESTALEIRO</div>
    <h1>Meus decks</h1>
    <p>Crie, edite e consulte as listas salvas na sua conta.</p>
  </div>
  <div class="cx-actions">
    <a class="cx-btn cx-btn-outline" href="<?= e(url('/decks.pdf', $filters)) ?>" target="_blank" rel="noopener">Exportar lista em PDF ↓</a>
    <a class="cx-btn cx-btn-gold" href="<?= e(url('/decks/novo')) ?>">+ Novo deck</a>
  </div>
</div>

<form class="cx-filters" method="get" action="<?= e(url('/decks')) ?>" role="search" aria-label="Filtrar decks">
  <label class="cx-field cx-field-wide">Nome
    <input type="search" name="q" value="<?= e($filters['q']) ?>" maxlength="100" placeholder="Buscar pelo nome do deck…">
  </label>
  <label class="cx-field">Formato
    <select name="format">
      <option value="">Todos</option>
      <?php foreach ($formats as $format): ?>
        <option value="<?= e($format['format_code']) ?>"<?= $filters['format'] === $format['format_code'] ? ' selected' : '' ?>><?= e($format['name_pt']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="cx-field">Situação
    <select name="status">
      <option value="">Todas</option>
      <?php foreach (Decks::STATUSES as $code => $label): ?>
        <option value="<?= e($code) ?>"<?= $filters['status'] === $code ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="cx-field">Visibilidade
    <select name="visibility">
      <option value="">Todas</option>
      <?php foreach (Decks::VISIBILITIES as $code => $label): ?>
        <option value="<?= e($code) ?>"<?= $filters['visibility'] === $code ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="cx-field">Cor do Líder
    <select name="color">
      <option value="">Todas</option>
      <?php foreach (Cards::COLORS as $code): ?>
        <option value="<?= e($code) ?>"<?= $filters['color'] === $code ? ' selected' : '' ?>><?= e(colorLabel($code)) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="cx-field">Ordenar por
    <select name="sort">
      <?php foreach (Decks::SORTS as $code => $label): ?>
        <option value="<?= e($code) ?>"<?= ($filters['sort'] ?: 'updated') === $code ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <div class="cx-filter-actions">
    <button type="submit" class="cx-btn cx-btn-gold">Filtrar</button>
    <?php if ($hasFilter || $filters['sort'] !== ''): ?><a class="cx-btn cx-btn-ghost" href="<?= e(url('/decks')) ?>">Limpar</a><?php endif; ?>
  </div>
</form>

<p class="cx-count" aria-live="polite"><?= e($result['total']) ?> deck<?= $result['total'] === 1 ? '' : 's' ?><?= $hasFilter ? ' encontrado' . ($result['total'] === 1 ? '' : 's') : '' ?></p>

<?php if ($result['rows'] === []): ?>
  <section class="cx-empty">
    <?php if ($hasFilter): ?>
      <h2>Nenhum deck com esses filtros.</h2>
      <p>Tente remover algum filtro para ver mais resultados.</p>
      <p><a class="cx-btn cx-btn-outline" href="<?= e(url('/decks')) ?>">Limpar filtros</a></p>
    <?php else: ?>
      <h2>Você ainda não montou nenhum deck.</h2>
      <p>Escolha um Líder, adicione cartas e acompanhe a curva de custo. Seu primeiro deck começa aqui.</p>
      <p><a class="cx-btn cx-btn-gold" href="<?= e(url('/decks/novo')) ?>">Criar meu primeiro deck</a></p>
    <?php endif; ?>
  </section>
<?php else: ?>
  <div class="cx-deck-grid">
    <?php foreach ($result['rows'] as $deck): $image = cardImage($deck['leader_image']); $ratio = min(100, (int)round($deck['main_quantity'] / Decks::MAIN_SIZE * 100)); ?>
      <article class="cx-deck">
        <a class="cx-deck-art" href="<?= e(url('/decks/' . $deck['public_id'])) ?>" tabindex="-1" aria-hidden="true">
          <?php if ($image): ?><img src="<?= e($image) ?>" alt="" width="120" height="168" loading="lazy"><?php else: ?><span class="cx-art-empty">Sem Líder</span><?php endif; ?>
        </a>
        <div class="cx-deck-body">
          <div class="cx-badges">
            <span class="cx-badge cx-badge-<?= e($deck['status']) ?>"><?= e(Decks::STATUSES[$deck['status']] ?? $deck['status']) ?></span>
            <span class="cx-badge"><?= e(Decks::VISIBILITIES[$deck['visibility']] ?? $deck['visibility']) ?></span>
            <span class="cx-badge"><?= e($deck['format_code'] === 'standard' ? 'Standard' : 'Estudo') ?></span>
          </div>
          <h2><a href="<?= e(url('/decks/' . $deck['public_id'])) ?>"><?= e($deck['name']) ?></a></h2>
          <p class="cx-leader">
            <?php foreach ($deck['leader_colors'] as $color): ?><span class="cx-dot" style="background:<?= e(colorHex($color)) ?>" title="<?= e(colorLabel($color)) ?>"></span><?php endforeach; ?>
            <?= $deck['leader_card_id'] === null ? 'Líder não escolhido' : e($deck['leader_card_id'] . ' · ' . $deck['leader_name']) ?>
          </p>
          <div class="cx-progress" role="img" aria-label="<?= e($deck['main_quantity']) ?> de <?= Decks::MAIN_SIZE ?> cartas no deck principal">
            <span style="width:<?= $ratio ?>%"></span>
          </div>
          <p class="cx-meta"><?= e($deck['main_quantity']) ?>/<?= Decks::MAIN_SIZE ?> cartas · atualizado em <?= e(formatDate((string)$deck['updated_at'])) ?></p>
          <div class="cx-row-actions">
            <a class="cx-btn cx-btn-ghost" href="<?= e(url('/decks/' . $deck['public_id'])) ?>">Ver</a>
            <a class="cx-btn cx-btn-outline" href="<?= e(url('/decks/' . $deck['public_id'] . '/editar')) ?>">Editar</a>
            <form method="post" action="<?= e(url('/decks/' . $deck['public_id'] . '/excluir')) ?>" class="inline-form" data-confirm="Excluir o deck &quot;<?= e($deck['name']) ?>&quot;? Esta ação não pode ser desfeita pela interface.">
              <?= csrfField() ?><button type="submit" class="cx-btn cx-btn-danger">Excluir</button>
            </form>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <?php if ($result['pages'] > 1): ?>
    <nav class="cx-pager" aria-label="Paginação">
      <?php if ($result['page'] > 1): ?><a class="cx-btn cx-btn-ghost" href="<?= e($pageUrl($result['page'] - 1)) ?>">← Anterior</a><?php endif; ?>
      <span>Página <?= e($result['page']) ?> de <?= e($result['pages']) ?></span>
      <?php if ($result['page'] < $result['pages']): ?><a class="cx-btn cx-btn-ghost" href="<?= e($pageUrl($result['page'] + 1)) ?>">Próxima →</a><?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
