<?php

require __DIR__ . '/init.php';

use Zend\Mail;
use Health\Db;
use Phugly as F;
use function Phugly\call;
use function Phugly\compose;
use function Phugly\map;
use function Phugly\filter;
use function Phugly\curry;
use function Phugly\getAt;
use function Phugly\setAt;
use function Phugly\ifElse;

$checkFrequency = new \DateInterval('PT5M');
$now = new \DateTimeImmutable();
$apiKey = getenv('API_KEY');

if(empty($apiKey)) return;
if(getAt('key', null, $_GET) !== $apiKey) return;

$compareBoolish = function($a, $b) {
    $normalize = function($x) { return $x === true || $x === '1' ? true : false; };

    return $normalize($a) === $normalize($b);
};

$shouldCheck = curry(function($now, $checkFrequency, $site) {
    if(empty($site['lastChecked'])) return true;

    $lastChecked = new \DateTimeImmutable($site['lastChecked']);

    return $now->sub($checkFrequency) >= $lastChecked;
});

$sendNotification = curry(function($emailTo, $emailFrom, $site) use ($compareBoolish) {
    // no email, can't send
    if(empty($emailTo) || empty($emailFrom)) return $site;

    // don't send if site is up
    if($site['result']['isUp']) return $site;

    // if the isUp status matches the last isUp status, don't send a notification because one has already been sent
    if($compareBoolish($site['result']['isUp'], $site['lastIsUp'])) return $site;

    ob_start();
?>
SITE DOWN

<?= $site['name']; ?>

<?= $site['url']; ?>

<?php
    $body = ob_get_clean();

    $mail = new Mail\Message();
    $mail->setSubject("HEALTH CHECK -- {$site['name']} DOWN");
    $mail->setBody($body);
    $mail->setFrom($emailFrom);
    $mail->addTo($emailTo);

    $transport = new Mail\Transport\Sendmail();
    $transport->send($mail);
});

$checkSite = function($timeout, $site) {
    $timeout = (int) $timeout;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $site['url']);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true); // don't need body
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $responseTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $errorNumber = curl_errno($ch);
    $errorMessage = curl_error($ch);
    curl_close($ch);

    return [
        'isUp' => !$errorNumber && $status === 200,
        'responseTime' => $responseTime,
        'error' => $errorNumber === 0 ? null : "${errorNumber} -- ${errorMessage}",
        'status' => $status,
    ];
};

// retry the check for any sites that failed
$retryCheck = map(
    ifElse(
        function($x) { return !getAt(['result', 'isUp'], true, $x); },
        function($x) use ($checkSite) { return setAt('result', $checkSite(getAt('SITE_CHECK_TIMEOUT', 10, $_ENV), $x), $x); },
        F\id
    )
);

// wait for a bit if any sites are down so we can do a retry
$waitIfDown = curry(function($waitSeconds, $infos) {
    $haveDown = call(compose(
        function($x) { return count($x) > 0; },
        filter(function($x) { return !getAt(['result', 'isUp'], true, $x); })
    ), $infos);

    if($haveDown) sleep($waitSeconds);

    return $infos;
});

$db = new Db(getenv('DB_DSN'), getenv('DB_USER'), getenv('DB_PASS'));

call(compose(
    map($sendNotification(getenv('NOTIFY_EMAIL_TO'), getenv('NOTIFY_EMAIL_FROM'))),
    map(function($x) use ($db) {
        $r = $x['result'];
        $db->addResult($x['id'], $r['isUp'], $r['responseTime'], $r['status'], $r['error']);
        return $x;
    }),
    $retryCheck,
    $waitIfDown(30),
    map(function($x) use ($checkSite) { return setAt('result', $checkSite(getAt('SITE_CHECK_TIMEOUT', 10, $_ENV), $x), $x); }),
    filter($shouldCheck($now, $checkFrequency))
), $db->getAllSites());
