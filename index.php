<?php

require __DIR__ . '/autoload.php';

use Health\Db;
use function Phugly\call;
use function Phugly\compose;
use function Phugly\curry;
use function Phugly\map;
use function Phugly\glue;
use function Phugly\getAt;
use function Phugly\setAt;

function esc($x) {
    return htmlspecialchars($x);
}

$renderSite = function($site) {
    $renderResult = function($result) {
        $className = $result['isUp'] ? 'is-up' : 'not-up';

        ob_start();
        ?>
        <div class='result <?= $className; ?>'>
            <div class='result-text'><?= esc($result['created']); ?></div>
        </div>
        <?php

        return ob_get_clean();
    };

    ob_start();
    ?>
    <div class='site'>
        <div class='name'><a href='<?= $site['url']; ?>'><?= esc($site['name']); ?></a></div>
        <div class='last-checked'><?= esc($site['lastChecked']); ?></div>
        <div class='results'>
            <?= call(compose(
                glue(''),
                map($renderResult)
            ), $site['results']); ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
};

$augResults = curry(function($db, $numResults, $site) {
    return setAt('results', $db->getResults($site['id'], $numResults), $site);
});

$db = new Db(getenv('DB_DSN'), getenv('DB_USER'), getenv('DB_PASS'));

$sites = call(compose(
    map($augResults($db, getAt('NUM_RESULTS', 20, $_ENV)))
), $db->getAllSites());

$xSmall = '@media (max-width: 450px)';
$small = '@media (max-width: 650px)';

?>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">

        <title>Health Check</title>

        <link href="https://fonts.googleapis.com/css?family=Roboto|Vollkorn" rel="stylesheet">


        <link rel='icon' type='image/png' sizes='256x256' href='./favicon-256.png' />
        <link rel='icon' type='image/png' sizes='1024x1024' href='./favicon-1024.png' />

        <style>
            html {
                box-sizing: border-box;
            }

            *, :after, :before {
                box-sizing: inherit;
            }

            body {
                margin: 0;
                padding: 40px;
                font-family: 'Roboto', sans-serif;
                background: #f3f5f7;
            }

            <?= $small; ?> {
                body {
                    padding: 20px;
                }
            }

            <?= $xSmall; ?> {
                body {
                    padding: 0;
                }
            }

            h1,
            h2,
            h3,
            h4 {
                font-family: 'Vollkorn', serif;
            }

            a,
            a:active,
            a:visited {
                text-decoration: none;
                color: #005eb3;
            }

            a:hover {
                opacity: 0.8;
            }

            #content {
                width: 900px;
                max-width: 100%;
                margin: 0 auto;
                padding: 40px 60px;
                background: white;
            }

            <?= $small; ?> {
                #content {
                    padding: 20px 20px;
                }
            }

            #content > h1 {
                margin-top: 0;
            }

            .site {
                display: flex;
                align-items: center;
                margin-bottom: 20px;
            }

            <?= $small; ?> {
                .site {
                    display: block;
                    margin-bottom: 40px;
                }
            }

            .last-checked {
                padding-left: 15px;
                color: #555;
                font-size: 14px;
            }

            <?= $small; ?> {
                .last-checked {
                    padding-left: 0;
                    margin-top: 3px;
                }
            }

            .results {
                display: flex;
                padding-left: 15px;
            }

            <?= $small; ?> {
                .results {
                    padding-left: 0;
                    margin-top: 5px;
                }
            }

            .results .result {
                width: 10px;
                height: 10px;
                margin-right: 5px;
                border-radius: 10px;
                position: relative
            }

            .results .result.is-up {
                background: #5bb36f;
            }

            .results .result.not-up {
                background: #f25255;
            }

            .results .result .result-text {
                display: none;
                position: absolute;
                top: -28px;
                left: -12px;
                background: #ddd;
                color: #857587;
                padding: 3px;
                border-radius: 3px;
                font-size: 12px;
                z-index: 1;
                min-width: 125px;
                text-align: center;
            }

            .results .result .result-text:after {
                content: '';
                display: block;
                position: absolute;
                left: 12px;
                bottom: -3px;
                background: #ddd;
                width: 10px;
                height: 10px;
                transform: rotate(45deg);
                z-index: -1;
            }

            .results .result:hover .result-text {
                display: block;
            }
        </style>

        <script>
            setTimeout(function() {
               window.location.reload(1)
            }, 310000)
        </script>
    </head>
    <body>
        <div id="content">
            <h1>Health Check</h1>

            <?= call(compose(
                glue(''),
                map($renderSite)
            ), $sites); ?>
        </div>
    </body>
</html>
