<?php
/** @var string $title @var string $nav @var ?array $user @var list<array{kind:string,message:string}> $flash @var string $content */
$items = [
    ['home', '/home.html', '▦', 'Explorar cartas'],
    ['decks', url('/decks'), '⚑', 'Meus decks'],
    ['builder', '/decks.html', '✎', 'Estaleiro (rascunho local)'],
    ['buy', '/comprar.html', '◉', 'Onde conseguir'],
    ['favorites', '/favoritos.html', '♡', 'Meus favoritos'],
    ['learn', '/aprender.html', '◈', 'Aprenda a jogar'],
];
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#101719">
<title><?= e($title) ?> — Grand Line Atlas</title>
<link rel="stylesheet" href="/assets/bootstrap.min.css"><link rel="stylesheet" href="/styles.css"><link rel="stylesheet" href="/pages.css"><link rel="stylesheet" href="/crew-pages.css"><link rel="stylesheet" href="/app/assets/crud.css">
</head><body class="atlas-app crud-app" data-page="<?= e($nav) ?>">
<a class="skip" href="#main">Pular para o conteúdo</a>
<aside class="app-sidebar" id="sidebar">
  <a class="brand" href="/index.html"><span class="brand-symbol">✦</span><span>GRAND LINE<span class="brand-sub">A T L A S</span></span></a>
  <span class="sidebar-kicker">SEU DIÁRIO DE BORDO</span>
  <nav class="side-nav" aria-label="Menu principal">
    <?php foreach ($items as [$id, $href, $icon, $label]): ?>
      <a href="<?= e($href) ?>"<?= $nav === $id ? ' class="selected" aria-current="page"' : '' ?>><span><?= e($icon) ?></span><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-course"><span>CONHECIMENTO É TESOURO</span><b>Sua primeira partida<br>começa aqui.</b><a href="/aprender.html">Conheça o jogo <span>↗</span></a><span class="sidebar-spark">✧</span></div>
  <div class="sidebar-bottom"><a href="/index.html">← Página de apresentação</a><span>Projeto acadêmico independente<br>One Piece TCG · Atlas</span></div>
</aside>
<button class="sidebar-scrim" aria-label="Fechar menu" hidden></button>
<header class="app-topbar">
  <button class="app-menu" aria-label="Abrir menu" aria-controls="sidebar" aria-expanded="false">☰</button>
  <span class="topbar-path">ATLAS <span>/</span> SUA CONTA</span>
  <div class="topbar-actions">
    <a href="/aprender.html" class="topbar-help" aria-label="Ajuda e tutorial">?</a>
    <?php if ($user): ?>
      <span class="topbar-user" title="<?= e($user['email']) ?>"><?= e($user['display_name']) ?></span>
      <form method="post" action="<?= e(url('/logout')) ?>" class="inline-form"><?= csrfField() ?><button type="submit" class="topbar-login linklike">Sair <span>↗</span></button></form>
    <?php else: ?>
      <a class="topbar-login" href="<?= e(url('/login')) ?>">Entrar <span>↗</span></a>
    <?php endif; ?>
  </div>
</header>
<main class="workspace" id="main">
  <?php foreach ($flash as $message): ?>
    <div class="cx-flash cx-flash-<?= e($message['kind']) ?>" role="<?= $message['kind'] === 'error' ? 'alert' : 'status' ?>"><?= e($message['message']) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
  <footer class="cx-footer">GRAND LINE ATLAS · Projeto acadêmico de fã para fã. One Piece © Eiichiro Oda / Shueisha / Toei Animation · Card Game © Bandai.</footer>
</main>
<script src="/app/assets/crud.js"></script>
</body></html>
