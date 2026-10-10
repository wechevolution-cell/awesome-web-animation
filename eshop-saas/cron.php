<?php
/**
 * CLI cron entry point. Hostinger hPanel → Advanced → Cron Jobs (every 15 minutes):
 *   php /home/USER/domains/DOMAIN/public_html/cron.php
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
define('ROOT', __DIR__);
require ROOT . '/app/bootstrap.php';
db();
echo json_encode(['time' => date('c'), 'result' => cron_run(50.0)], JSON_UNESCAPED_SLASHES) . PHP_EOL;
