<?php

use Symfony\Component\Dotenv\Dotenv;

$backendDir = dirname(__DIR__, 2).'/backend';
$autoload = $backendDir.'/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "ERREUR : vendor/autoload.php est absent. Exécutez composer install avant ce contrôle.\n");
    exit(1);
}

require $autoload;

(new Dotenv())->bootEnv($backendDir.'/.env');

$command = $argv[1] ?? 'check';

if ($command === 'print-uri') {
    $uri = envValue('DEFAULT_URI');
    if (!preg_match('#^https://#', $uri) || str_contains($uri, 'localhost') || str_contains($uri, 'example.com')) {
        fwrite(STDERR, "ERREUR : DEFAULT_URI n'est pas une URL HTTPS de production.\n");
        exit(1);
    }

    echo $uri;
    exit(0);
}

if ($command === 'write-cnf') {
    $target = $argv[2] ?? '';
    if ($target === '') {
        fwrite(STDERR, "ERREUR : chemin du fichier client MariaDB absent.\n");
        exit(1);
    }

    $database = databaseParts();
    if ($database['name'] !== 'diare_groupe_industrie' || $database['user'] !== 'diare_prod') {
        fwrite(STDERR, "ERREUR : la sauvegarde refuse une base autre que diare_groupe_industrie.\n");
        exit(1);
    }

    $content = "[client]\n".
        'user="'.escapeCnf($database['user'])."\"\n".
        'password="'.escapeCnf($database['password'])."\"\n".
        'host="'.escapeCnf($database['host'])."\"\n".
        'port='.$database['port']."\n";

    if (file_put_contents($target, $content) === false) {
        fwrite(STDERR, "ERREUR : impossible d'écrire le fichier d'identifiants MariaDB.\n");
        exit(1);
    }

    chmod($target, 0600);
    exit(0);
}

if ($command !== 'check') {
    fwrite(STDERR, "ERREUR : commande inconnue.\n");
    exit(1);
}

$failed = false;

$failed = !check('APP_ENV', envValue('APP_ENV') === 'prod') || $failed;
$debug = strtolower(envValue('APP_DEBUG'));
$failed = !check('APP_DEBUG', in_array($debug, ['0', 'false'], true)) || $failed;

$secret = envValue('APP_SECRET');
$failed = !check('APP_SECRET', $secret !== '' && !str_contains($secret, 'change-me') && strlen($secret) >= 32) || $failed;

$uri = envValue('DEFAULT_URI');
$failed = !check('DEFAULT_URI', preg_match('#^https://#', $uri) === 1 && !str_contains($uri, 'localhost') && !str_contains($uri, 'example.com')) || $failed;

try {
    $database = databaseParts();
    $failed = !check('DATABASE_URL', true) || $failed;
    $failed = !check('DATABASE_NAME', $database['name'] === 'diare_groupe_industrie') || $failed;
    $failed = !check('DATABASE_USER', $database['user'] === 'diare_prod') || $failed;
} catch (RuntimeException) {
    echo "INVALIDE DATABASE_URL\n";
    $failed = true;
}

$dsn = envValue('MAILER_DSN');
$mailerOk = $dsn === 'brevo+api://default';
$failed = !check('MAILER_DSN', $mailerOk) || $failed;
$apiKey = envValue('BREVO_API_KEY');
$failed = !check('BREVO_API_KEY', $apiKey !== '' && !str_contains($apiKey, 'CHANGE_ME')) || $failed;
$failed = !check('MAIL_FROM_EMAIL', filter_var(envValue('MAIL_FROM_EMAIL'), FILTER_VALIDATE_EMAIL) !== false && !str_contains(envValue('MAIL_FROM_EMAIL'), 'example.com')) || $failed;
$failed = !check('MAIL_FROM_NAME', envValue('MAIL_FROM_NAME') !== '') || $failed;

$messenger = envValue('MESSENGER_TRANSPORT_DSN');
$failed = !check('MESSENGER_TRANSPORT_DSN', str_starts_with($messenger, 'doctrine://default') && str_contains($messenger, 'auto_setup=0')) || $failed;

$recaptchaEnabled = envValue('RECAPTCHA_ENABLED');
if (!in_array($recaptchaEnabled, ['0', '1'], true)) {
    $failed = !check('RECAPTCHA_ENABLED', false) || $failed;
} elseif ($recaptchaEnabled === '1') {
    $failed = !check('RECAPTCHA_SITE_KEY', envValue('RECAPTCHA_SITE_KEY') !== '') || $failed;
    $failed = !check('RECAPTCHA_SECRET_KEY', envValue('RECAPTCHA_SECRET_KEY') !== '') || $failed;
} else {
    echo "OK RECAPTCHA_ENABLED desactive\n";
}

$analytics = envValue('GOOGLE_ANALYTICS_MEASUREMENT_ID');
if ($analytics === '') {
    echo "OK GOOGLE_ANALYTICS desactive\n";
} else {
    $failed = !check('GOOGLE_ANALYTICS_MEASUREMENT_ID', preg_match('/^G-[A-Z0-9]+$/', $analytics) === 1) || $failed;
}

$indexable = envValue('APP_INDEXABLE');
$failed = !check('APP_INDEXABLE', in_array($indexable, ['0', '1'], true)) || $failed;

exit($failed ? 1 : 0);

function envValue(string $name): string
{
    $value = $_ENV[$name] ?? $_SERVER[$name] ?? '';

    return trim((string) $value);
}

function check(string $name, bool $valid): bool
{
    echo ($valid ? 'OK ' : 'INVALIDE ').$name."\n";

    return $valid;
}

/** @return array{user: string, password: string, host: string, port: int, name: string} */
function databaseParts(): array
{
    $url = envValue('DATABASE_URL');
    $parts = parse_url($url);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'mysql') {
        throw new RuntimeException('DATABASE_URL illisible.');
    }

    $name = rawurldecode(trim((string) ($parts['path'] ?? ''), '/'));
    $user = rawurldecode((string) ($parts['user'] ?? ''));
    $password = rawurldecode((string) ($parts['pass'] ?? ''));
    $host = (string) ($parts['host'] ?? '');
    $port = (int) ($parts['port'] ?? 3306);

    if ($user === '' || $user === 'root' || $password === '' || $password === 'CHANGE_ME' || $host === '' || $name === '') {
        throw new RuntimeException('DATABASE_URL incomplète.');
    }

    return [
        'user' => $user,
        'password' => $password,
        'host' => $host,
        'port' => $port,
        'name' => $name,
    ];
}

function escapeCnf(string $value): string
{
    if (str_contains($value, "\n") || str_contains($value, "\r")) {
        throw new RuntimeException('Identifiant MariaDB illisible.');
    }

    return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
}
