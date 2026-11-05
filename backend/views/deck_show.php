<?php
use Atlas\Decks;

/** @var array<string,mixed> $deck @var list<array> $items @var array<string,int> $totals @var array<int,int> $curve @var list<array> $issues */
$image = cardImage($deck['leader_image']);
$peak = max(1, ...array_values($curve));
$messages = array_values(array_unique(array_column($issues, 'message')));
$base = url('/decks/' . $deck['public_id']);
?>
<div class="workspace-intro">
  <div>
    <div class="eyebrow gold"><a href="<?= e(url('/decks')) ?>" class="cx-crumb">← MEUS DECKS</a></div>
    <h1><?= e($deck['name']) ?></h1>
    <div class="cx-badges">
      <span class="cx-badge cx-badge-<?= e($deck['status']) ?>"><?= e(Decks::STATUSES[$deck['status']] ?? $deck['status']) ?></span>
      <span class="cx-badge"><?= e(Decks::VISIBILITIES[$deck['visibility']] ?? $deck['visibility']) ?></span>
      <span class="cx-badge"><?= e($deck['format_name']) ?></span>
    </div>
  </div>
  <div class="cx-actions">
    <a class="cx-btn cx-btn-outline" href="<?= e($base . '.pdf') ?>" target="_blank" rel="noopener">Lista em PDF ↓</a>
    <a class="cx-btn cx-btn-gold" href="<?= e($base . '/editar') ?>">Editar deck</a>
    <form method="post" action="<?= e($base . '/excluir') ?>" class="inline-form" data-confirm="Excluir o deck &quot;<?= e($deck['name']) ?>&quot;? Esta ação não pode ser desfeita pela interface.">
      <?= csrfField() ?><button type="submit" class="cx-btn cx-btn-danger">Excluir</button>
    </form>
  </div>
</div>

<div class="cx-show-grid">
  <section class="cx-panel cx-leader-panel" aria-labelledby="leader-h">
    <h2 id="leader-h" class="cx-panel-title">Líder</h2>
    <?php if ($deck['leader_card_id'] === null): ?>
      <p class="cx-muted">Nenhum Líder escolhido ainda.</p>
      <a class="cx-btn cx-btn-outline" href="<?= e($base . '/editar') ?>">Escolher Líder</a>
    <?php else: ?>
      <div class="cx-leader-card">
        <?php if ($image): ?><img src="<?= e($image) ?>" alt="<?= e($deck['leader_name']) ?> — <?= e($deck['leader_card_id']) ?>" width="180" height="252"><?php endif; ?>
        <div>
          <b><?= e($deck['leader_name']) ?></b>
          <p class="cx-muted"><?= e($deck['leader_card_id']) ?></p>
          <p><?php foreach ($deck['leader_colors'] as $color): ?><span class="cx-dot" style="background:<?= e(colorHex($color)) ?>"></span> <?= e(colorLabel($color)) ?> <?php endforeach; ?></p>
        </div>
      </div>
    <?php endif; ?>
  </section>

  <section class="cx-panel" aria-labelledby="sum-h">
    <h2 id="sum-h" class="cx-panel-title">Resumo</h2>
    <dl class="cx-stats">
      <div><dt>Deck principal</dt><dd><?= e($totals['main_quantity']) ?><small>/<?= Decks::MAIN_SIZE ?></small></dd></div>
      <div><dt>Personagens</dt><dd><?= e($totals['character_quantity']) ?></dd></div>
      <div><dt>Eventos</dt><dd><?= e($totals['event_quantity']) ?></dd></div>
      <div><dt>Cenários</dt><dd><?= e($totals['stage_quantity']) ?></dd></div>
      <div><dt>Com Counter</dt><dd><?= e($totals['counter_quantity']) ?></dd></div>
      <div><dt>DON!!</dt><dd><?= e($deck['don_quantity']) ?><small>/10</small></dd></div>
    </dl>
    <h3 class="cx-sub">Curva de custo</h3>
    <div class="cx-curve" role="img" aria-label="Curva de custo do deck">
      <?php foreach ($curve as $cost => $quantity): ?>
        <div class="cx-curve-col"><span class="cx-curve-n"><?= e($quantity) ?></span><span class="cx-curve-bar" style="height:<?= (int)round($quantity / $peak * 100) ?>%"></span><span class="cx-curve-x"><?= $cost >= 6 ? '6+' : e($cost) ?></span></div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="cx-panel" aria-labelledby="status-h">
    <h2 id="status-h" class="cx-panel-title">Estrutura</h2>
    <?php if ($messages === []): ?>
      <p class="cx-ok">✓ Estrutura básica válida: Líder, 50 cartas, 10 DON!!, limite por ID e cores do Líder.</p>
    <?php else: ?>
      <p class="cx-muted">Pendências para o deck ficar pronto:</p>
      <ul class="cx-issues"><?php foreach ($messages as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <p class="cx-note">Verificação de estudo. Não certifica legalidade em torneios: banidas, blocos e idiomas dependem do regulamento vigente.</p>
    <?php if ($deck['description']): ?><h3 class="cx-sub">Anotações</h3><p class="cx-desc"><?= nl2br(e($deck['description'])) ?></p><?php endif; ?>
    <p class="cx-meta">Criado em <?= e(formatDate((string)$deck['created_at'])) ?> · atualizado em <?= e(formatDate((string)$deck['updated_at'])) ?> · revisão <?= e($deck['revision']) ?></p>
  </section>
</div>

<section class="cx-panel" aria-labelledby="cards-h">
  <h2 id="cards-h" class="cx-panel-title">Cartas do deck principal</h2>
  <?php if ($items === []): ?>
    <p class="cx-muted">Nenhuma carta adicionada ainda.</p>
    <a class="cx-btn cx-btn-gold" href="<?= e($base . '/editar') ?>">Adicionar cartas</a>
  <?php else: ?>
    <div class="cx-table-wrap">
      <table class="cx-table">
        <thead><tr><th scope="col" class="num">Qtd.</th><th scope="col">Carta</th><th scope="col">Tipo</th><th scope="col" class="num">Custo</th><th scope="col">Cor</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): $thumb = cardImage($item['image_path']); ?>
          <tr>
            <td class="num"><b><?= e($item['quantity']) ?>×</b></td>
            <td><div class="cx-cardcell"><?php if ($thumb): ?><img src="<?= e($thumb) ?>" alt="" width="36" height="50" loading="lazy"><?php endif; ?><div><b><?= e($item['name']) ?></b><small><?= e($item['card_id']) ?></small></div></div></td>
            <td><?= e(typeLabel((string)$item['type_code'])) ?></td>
            <td class="num"><?= $item['cost'] === null ? '—' : e($item['cost']) ?></td>
            <td><?php foreach ($item['colors'] as $color): ?><span class="cx-dot" style="background:<?= e(colorHex($color)) ?>" title="<?= e(colorLabel($color)) ?>"></span><?php endforeach; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
