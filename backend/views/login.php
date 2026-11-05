<?php /** @var string $next @var ?string $error @var string $email */ ?>
<span class="eyebrow gold">BOM TER VOCÊ DE VOLTA</span>
<h2>Vamos embarcar?</h2>
<p class="account-intro">Entre na sua conta para acessar seus decks.</p>
<?php if ($error): ?><div class="cx-flash cx-flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('/login')) ?>" class="cx-auth-form" novalidate>
  <?= csrfField() ?>
  <input type="hidden" name="next" value="<?= e($next) ?>">
  <label for="email">E-mail</label>
  <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="email" maxlength="254" placeholder="voce@exemplo.com" required autofocus>
  <label for="password">Senha</label>
  <div class="password-input">
    <input type="password" id="password" name="password" autocomplete="current-password" maxlength="128" placeholder="Sua senha" required>
    <button type="button" data-toggle-password="password" aria-label="Mostrar senha" aria-pressed="false">Mostrar</button>
  </div>
  <button type="submit" class="cx-btn cx-btn-gold cx-btn-block">Entrar no Atlas <span>↗</span></button>
</form>
<div class="auth-divider"><span>ou</span></div>
<a href="/home.html" class="guest-button">Explorar sem entrar <span>→</span></a>
<p class="auth-alternative">Ainda não embarcou? <a href="<?= e(url('/cadastro')) ?>">Criar uma conta</a></p>
