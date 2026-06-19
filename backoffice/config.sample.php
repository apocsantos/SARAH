<?php
// Copia para config.php e ajusta se necessário.
define('SARAH_BACKOFFICE_INSTALLED', true);

// Caminhos principais
define('SARAH_STORAGE_DIR', __DIR__ . '/storage');
define('SARAH_ICONS_DIR', SARAH_STORAGE_DIR . '/icons');
define('SARAH_SEED_FILE', SARAH_STORAGE_DIR . '/seed.json');

// Segurança
define('SARAH_SESSION_NAME', 'SARAH_BACKOFFICE');
define('SARAH_REQUIRE_2FA', true);

// Se quiseres desativar CAPTCHA simples, coloca false.
define('SARAH_ENABLE_CAPTCHA', true);

// Fuso horário
date_default_timezone_set('Europe/Lisbon');
