<?php
declare(strict_types=1);

/**
 * Roteador-invólucro usado apenas pelos testes HTTP: injeta o SQLite de teste e delega ao
 * backend/router.php real (o código de produção não tem nenhum gancho de teste).
 */
require __DIR__ . '/prepend.php';

return require __DIR__ . '/../router.php';
