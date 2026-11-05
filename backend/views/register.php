<?php /** @var array<string,string> $errors @var array{name:string,email:string} $old */ ?>
<span class="eyebrow gold">NOVO TRIPULANTE</span>
<h2>Crie sua conta</h2>
<p class="account-intro">Guarde seus decks e acesse de qualquer dispositivo.</p>
<form method="post" action="<?= e(url('/cadastro')) ?>" class="cx-auth-form" novalidate>
  <?= csrfField() ?>
  <label for="name">Nome</label>
  <input type="text" id="name" name="name" value="<?= e($old['name']) ?>" autocomplete="name" maxlength="100" placeholder="Como devemos chamar você?" required autofocus aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>">
  <?php if (isset($errors['name'])): ?><p class="cx-field-error"><?= e($errors['name']) ?></p><?php endif; ?>

  <label for="email">E-mail</label>
  <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" autocomplete="email" maxlength="254" placeholder="voce@exemplo.com" required aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>">
  <?php if (isset($errors['email'])): ?><p class="cx-field-error"><?= e($errors['email']) ?></p><?php endif; ?>

  <label for="password">Senha</label>
  <div class="password-input">
    <input type="password" id="password" name="password" autocomplete="new-password" maxlength="128" placeholder="Pelo menos 8 caracteres" required aria-invalid="<?= isset($errors['password']) ? 'true' : 'false' ?>">
    <button type="button" data-toggle-password="password" aria-label="Mostrar senha" aria-pressed="false">Mostrar</button>
  </div>
  <?php if (isset($errors['password'])): ?><p class="cx-field-error"><?= e($errors['password']) ?></p><?php endif; ?>

  <label for="password_confirm">Confirmar senha</label>
  <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" maxlength="128" placeholder="Repita a senha" required aria-invalid="<?= isset($errors['password_confirm']) ? 'true' : 'false' ?>">
  <?php if (isset($errors['password_confirm'])): ?><p class="cx-field-error"><?= e($errors['password_confirm']) ?></p><?php endif; ?>

  <button type="submit" class="cx-btn cx-btn-gold cx-btn-block">Criar conta <span>↗</span></button>
</form>
<p class="auth-alternative">Já tem conta? <a href="<?= e(url('/login')) ?>">Entrar</a></p>
