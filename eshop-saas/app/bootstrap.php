<?php
declare(strict_types=1);

const APP_VERSION = '1.0.0';
if (!defined('ROOT')) define('ROOT', dirname(__DIR__));
define('APP', ROOT . '/app');
define('DATA', ROOT . '/data');
define('UPLOADS', ROOT . '/uploads');
define('VIEWS', APP . '/views');

$debug = getenv('APP_DEBUG') === '1' || is_file(DATA . '/.debug');
define('APP_DEBUG', $debug);
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
mb_internal_encoding('UTF-8');

foreach (['helpers', 'db', 'sanitize', 'markdown', 'auth', 'tenancy', 'i18n', 'router'] as $f) {
    require APP . "/$f.php";
}
foreach (glob(APP . '/engines/*.php') as $f) require $f;
foreach (glob(APP . '/controllers/*.php') as $f) require $f;

set_exception_handler('app_exception_handler');
set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
    if (!(error_reporting() & $no)) return false;
    throw new ErrorException($str, 0, $no, $file, $line);
});

date_default_timezone_set('Europe/Athens');
