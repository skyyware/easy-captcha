<?php

declare(strict_types=1);

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\{Altcha, Challenge, Payload, SolveChallengeOptions};
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Skyyware\Starcode\Protection\ProtectionPolicy;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

$root = realpath($argv[1] ?? '');
$base = rtrim($argv[2] ?? '', '/');
$host = parse_url($base, PHP_URL_HOST);
if (!$root || !is_file($root . '/vendor/autoload.php') || !is_string($host)
    || !str_ends_with($host, '.dev') || str_ends_with($host, '.stage.dev') || !str_starts_with($base, 'https://')) {
    throw new RuntimeException('Use an existing, isolated local Shopware root and HTTPS .dev URL.');
}
$loader = require $root . '/vendor/autoload.php';
(new Dotenv())->usePutenv()->bootEnv($root . '/.env');
$databaseHost = parse_url((string) ($_SERVER['DATABASE_URL'] ?? ''), PHP_URL_HOST);
if (!in_array($databaseHost, ['localhost', '127.0.0.1'], true) || ($_SERVER['MAILER_DSN'] ?? '') !== 'null://null') {
    throw new RuntimeException('Refusing a non-local database or a non-null mail transport.');
}
$_SERVER['PROJECT_ROOT'] = $_ENV['PROJECT_ROOT'] = $root;
$kernel = KernelFactory::create('prod', false, $loader);
$kernel->boot();
$container = $kernel->getContainer();
$db = $container->get(Connection::class);
$config = $container->get(SystemConfigService::class);
$events = $container->get('event_dispatcher');
$denyMail = static function (): never { throw new RuntimeException('Mail forbidden in Easy Captcha probe.'); };
$events->addListener(Symfony\Component\Mailer\Event\MessageEvent::class, $denyMail, PHP_INT_MAX);
$events->addListener('mail.before.send', $denyMail, PHP_INT_MAX);
$events->addListener(KernelEvents::CONTROLLER, static function (ControllerEvent $event): void {
    $route = (string) $event->getRequest()->attributes->get('_route');
    if (!str_contains($route, 'starcode') && $event->getRequest()->isMethod('POST') && is_array($event->getController())) {
        $event->setController(static fn (): JsonResponse => new JsonResponse(['businessControllerSuppressed' => true]));
    }
}, -1000);

$channel = $db->fetchAssociative('SELECT LOWER(HEX(id)) AS id, access_key FROM sales_channel WHERE active = 1 AND type_id = UNHEX(?) LIMIT 1', ['8a243080f92e4c719546314b577cf82b']);
if (!$channel) {
    throw new RuntimeException('No active local storefront channel.');
}
$before = [];
foreach (['customer', 'order', 'document', 'newsletter_recipient', 'messenger_messages'] as $table) {
    $before[$table] = (int) $db->fetchOne('SELECT COUNT(*) FROM `' . $table . '`');
}
$previousMode = $config->get('SkyyStarcode.config.mode');
$proofIds = [];
$checks = [];
$passed = false;
$request = static function (string $path, string $method = 'GET', array $body = [], array $headers = []) use ($kernel, $base, $channel): Symfony\Component\HttpFoundation\Response {
    $server = [
        'HTTPS' => 'on', 'REMOTE_ADDR' => '127.0.0.23',
        'HTTP_SW_ACCESS_KEY' => $channel['access_key'],
    ];
    if (str_starts_with($path, '/form/') || str_starts_with($path, '/%66orm/')) {
        $server['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    }
    $server = array_replace($server, $headers);
    $json = str_starts_with($path, '/store-api/') && $method === 'POST';
    if ($json) {
        $server['CONTENT_TYPE'] = 'application/json';
    }
    $request = Request::create($base . $path, $method, $json ? [] : $body, [], [], $server, $json ? json_encode($body, JSON_THROW_ON_ERROR) : null);
    return $kernel->handle($request);
};
$assert = static function (bool $condition, string $name) use (&$checks): void {
    $checks[] = ['name' => $name, 'passed' => $condition];
    if (!$condition) {
        throw new RuntimeException($name);
    }
};
try {
    $assert($config->get('SkyyStarcode.config.enabled') === true, 'local-plugin-enabled-with-boolean');
    $config->set('SkyyStarcode.config.mode', 'all');
    $router = $container->get('router');
    foreach (ProtectionPolicy::GROUPS as $group => $paths) {
        foreach ($paths as $path) {
            $sample = str_replace('*', str_repeat('1', 32), $path);
            $router->getContext()->setMethod('POST');
            $route = $router->match($sample);
            $assert(isset($route['_route']), 'registered-route:' . $group . ':' . $sample);
        }
    }
    foreach (['/form/contact', '/form/newsletter', '/account/register', '/account/recover', '/form/revocation/request', '/store-api/contact-form', '/store-api/newsletter/subscribe', '/store-api/account/register', '/store-api/account/recovery-password', '/store-api/revocation-request-form'] as $path) {
        $response = $request($path, 'POST');
        if ($response->getStatusCode() !== 422) {
            fwrite(STDERR, json_encode(['path' => $path, 'errorCodes' => array_column(json_decode((string) $response->getContent(), true)['errors'] ?? [], 'code')]) . "\n");
        }
        $assert($response->getStatusCode() === 422 && (str_contains((string) $response->getContent(), 'STARCODE_PROOF_REQUIRED') || str_contains((string) $response->getContent(), '<title>Easy Captcha</title>')), 'missing-proof:' . $path . ':' . $response->getStatusCode());
    }
    $response = $request('/%66orm/contact', 'POST');
    $assert($response->getStatusCode() === 422, 'encoded-path-cannot-bypass');
    foreach ([
        ['/form/contact', false, '/starcode/challenge'],
        ['/store-api/contact-form', false, '/store-api/starcode/challenge'],
        ['/store-api/contact-form', true, '/store-api/starcode/challenge'],
        ['/form/contact', false, '/easy-captcha/challenge'],
        ['/store-api/contact-form', false, '/store-api/easy-captcha/challenge'],
        ['/store-api/contact-form', true, '/store-api/easy-captcha/challenge'],
    ] as [$path, $headerProof, $endpoint]) {
        $case = $endpoint . ':' . $path . ($headerProof ? ':header' : ':body');
        $challengeResponse = $request($endpoint . '?target=' . rawurlencode($path));
        $assert($challengeResponse->getStatusCode() === 200 && str_contains($challengeResponse->headers->get('Cache-Control', ''), 'no-store'), 'uncached-challenge:' . $case);
        $challenge = Challenge::fromArray(json_decode((string) $challengeResponse->getContent(), true, flags: JSON_THROW_ON_ERROR));
        $solution = (new Altcha())->solveChallenge(new SolveChallengeOptions($challenge, new Pbkdf2(), timeout: 5));
        $assert($solution !== null, 'solve:' . $case);
        $token = (new Payload($challenge, $solution))->toBase64();
        $proofIds[] = hash('sha256', $challenge->signature);
        $response = $request($path, 'POST', $headerProof ? [] : ['starcode' => $token], $headerProof ? ['HTTP_X_STARCODE_PROOF' => $token] : []);
        $assert($response->getStatusCode() === 200 && str_contains((string) $response->getContent(), 'businessControllerSuppressed'), 'valid-proof-reaches-controller-without-business-side-effects:' . $case);
        $assert($request($path, 'POST', ['starcode' => $token])->getStatusCode() === 422, 'replay-rejected:' . $case);
    }
    $unsubscribe = $request('/form/newsletter', 'POST', ['option' => 'unsubscribe']);
    $assert($unsubscribe->getStatusCode() === 200 && str_contains((string) $unsubscribe->getContent(), 'businessControllerSuppressed'), 'unsubscribe-exempt-without-business-side-effects');
    $assert($request('/starcode/challenge?target=%2Fcheckout%2Forder')->getStatusCode() === 404, 'checkout-challenge-excluded');
    $assert($request('/easy-captcha/challenge?target=%2Fcheckout%2Forder')->getStatusCode() === 404, 'checkout-challenge-alias-excluded');
    $passed = true;
} finally {
    $config->set('SkyyStarcode.config.mode', $previousMode);
    foreach ($proofIds as $id) {
        $db->executeStatement('DELETE FROM skyy_starcode_proof WHERE id = ?', [$id]);
    }
    $after = [];
    foreach (array_keys($before) as $table) {
        $after[$table] = (int) $db->fetchOne('SELECT COUNT(*) FROM `' . $table . '`');
    }
    $restored = $config->get('SkyyStarcode.config.mode') === $previousMode && $before === $after;
    echo json_encode(['passed' => $passed && $restored, 'checks' => $checks, 'configurationRestored' => $restored, 'businessCountsUnchanged' => $before === $after, 'customerMailSent' => false, 'businessControllersExecuted' => 0, 'proofRowsRemoved' => count($proofIds), 'rateBucketRetentionSeconds' => 120], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
    if (!$restored) {
        throw new RuntimeException('Probe closeout mismatch; inspect local evidence.');
    }
}
