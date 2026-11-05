<?php /** @var int $code @var string $message */ ?>
<section class="cx-empty">
  <span class="eyebrow gold">ERRO <?= e($code) ?></span>
  <h1><?= e($title) ?></h1>
  <p><?= e($message) ?></p>
  <p><a class="cx-btn cx-btn-outline" href="<?= e(url('/decks')) ?>">← Voltar para meus decks</a></p>
</section>
