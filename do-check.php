<?php

require __DIR__ . '/autoload.php';

use Zend\Mail;
use Health\Db;
use function Phugly\call;
use function Phugly\compose;
use function Phugly\map;
use function Phugly\filter;
use function Phugly\curry;
use function Phugly\getAt;
use function Phugly\setAt;

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
    if($site['isUp']) return $site;

    // if the isUp status matches the last isUp status, don't send a notification because one has already been sent
    if($compareBoolish($site['isUp'], $site['lastIsUp'])) return $site;

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

$siteIsUp = function($site) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $site['url']);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true); // don't need body
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_errno($ch);
    curl_close($ch);

    return $error || $status === 200;
};

$db = new Db(getenv('DB_DSN'), getenv('DB_USER'), getenv('DB_PASS'));

call(compose(
    map($sendNotification(getenv('NOTIFY_EMAIL_TO'), getenv('NOTIFY_EMAIL_FROM'))),
    map(function($x) use ($db) {
        $db->addResult($x['id'], $x['isUp']);
        return $x;
    }),
    map(function($x) use ($siteIsUp) { return setAt('isUp', $siteIsUp($x), $x); }),
    filter($shouldCheck($now, $checkFrequency))
), $db->getAllSites());
