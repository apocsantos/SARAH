<?php
define('SARAH_BACKOFFICE_INSTALLED', true);
define('SARAH_STORAGE_DIR', __DIR__ . '/storage');
define('SARAH_ICONS_DIR', SARAH_STORAGE_DIR . '/icons');
define('SARAH_SEED_FILE', SARAH_STORAGE_DIR . '/seed.json');
define('SARAH_SESSION_NAME', 'SARAH_BACKOFFICE');
define('SARAH_REQUIRE_2FA', false);
define('SARAH_ENABLE_CAPTCHA', true);
date_default_timezone_set('Europe/Lisbon');
