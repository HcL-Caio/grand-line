<?php
/** @var string $title @var list<array{kind:string,message:string}> $flash @var string $content */
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#101719">
<title><?= e($title) ?> — Grand Line Atlas</title>
<link rel="stylesheet" href="/assets/bootstrap.min.css"><link rel="stylesheet" href="/styles.css"><link rel="stylesheet" href="/pages.css"><link rel="stylesheet" href="/crew-pages.css"><link rel="stylesheet" href="/app/assets/crud.css">
</head><body class="auth-page crud-auth">
<a class="skip" href="#auth-main">Pular para o formulário</a>
<div class="auth-layout">
  <section class="auth-scene" aria-label="Grand Line Atlas">
    <div class="auth-cover-wall" aria-hidden="true"><?php for ($i = 0; $i < 12; $i++): ?><img src="/assets/cover-<?= $i ?>.jpg" alt=""><?php endfor; ?></div>
    <div class="auth-scene-shade"></div>
    <a class="brand" href="/index.html"><span class="brand-symbol">✦</span><span>GRAND LINE<span class="brand-sub">A T L A S</span></span></a>
    <div class="auth-scene-copy"><span class="eyebrow gold">CADA CARTA TEM UMA HISTÓRIA.</span><h1>A sua aventura<br>merece um<br><em>novo capítulo.</em></h1><p>Monte seus decks, guarde-os na sua conta<br>e leve a lista para a mesa.</p></div>
    <div class="auth-scene-footer">UM GUIA DE FÃ PARA FÃ <span>GRAND LINE · NOVO MUNDO</span></div>
  </section>
  <main class="auth-main" id="auth-main">
    <a class="auth-back" href="/index.html">← Voltar ao convés</a>
    <div class="account-box">
      <?php foreach ($flash as $message): ?>
        <div class="cx-flash cx-flash-<?= e($message['kind']) ?>" role="status"><?= e($message['message']) ?></div>
      <?php endforeach; ?>
      <?= $content ?>
    </div>
    <small class="auth-bottom-note">Projeto acadêmico independente · One Piece © seus respectivos titulares.</small>
  </main>
</div>
<script src="/app/assets/crud.js"></script>
</body></html>
