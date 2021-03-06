<?php

require __DIR__ . '/init.php';

use Health\Db;
use function Phugly\call;
use function Phugly\compose;
use function Phugly\curry;
use function Phugly\map;
use function Phugly\glue;
use function Phugly\getAt;
use function Phugly\setAt;
use function Phugly\firstN;

function esc($x) {
    return htmlspecialchars($x);
}

function randStr($length=20) {
    $characters = "abcdefghijklmonpqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

    $str = "";
    for($i=0; $i < $length; $i++) $str .= substr($characters, rand(0, strlen($characters) - 1), 1);
    return $str;
}


$renderSite = curry(function($numResults, $site) {
    $renderResult = function($result, $isLastDown = false) {
        if(empty($result)) return '';

        $className = call(compose(
            glue(' '),
            'array_filter'
        ), [
            $result['isUp'] ? 'is-up' : 'not-up',
            $isLastDown ? 'last-down' : null,
        ]);

        $resultText = call(compose(
            glue('&nbsp;|&nbsp;'),
            'array_filter'
        ), [
            esc($result['created']),
            empty($result['responseTime']) ? null : esc($result['responseTime']),
            empty($result['status']) ? null : esc($result['status']),
        ]);

        ob_start();
        ?>
        <div class='result <?= $className; ?>'>
            <div class='result-text'><?= $resultText; ?></div>
        </div>
        <?php

        return ob_get_clean();
    };

    $renderChart = function($site) {
        $chartId = 'chart-' . randStr(12);

        $labelsJson = call(compose(
            'json_encode',
            map(function($x) { return ''; }),
            map(getAt('created', ''))
        ), $site['results']);

        $responseTimesJson = call(compose(
            'json_encode',
            map(function($x) {
                $meta = call(compose(
                    glue(' | '),
                    'array_filter'
                ), [
                    esc($x['created']),
                    empty($x['responseTime']) ? null : esc($x['responseTime']),
                    empty($x['status']) ? null : esc($x['status']),
                ]);

                return [
                    'value' => getAt('responseTime', 0, $x),
                    'meta' => $meta,
                ];
            })
        ), $site['results']);

        ob_start();
        ?>
        <div class='chart' id='<?= $chartId; ?>'>
            <div class='chart-container'></div>
        </div>
        <script>
            new Chartist.Line('#<?= $chartId; ?> .chart-container', {
                    // labels: <?= $labelsJson; ?>,
                    series: [<?= $responseTimesJson; ?>],
                }, {
                    fullWidth: true,
                    height: '200px',
                },
            )
                .on('created', () => {
                    document.querySelectorAll('#<?= $chartId; ?> .ct-point').forEach(x => {
                        let hoverEl

                        x.addEventListener('mouseover', e => {
                            const el = e.currentTarget
                                console.log(el.clientX, el.clientY)
                            el.classList.add('hovered')

                            const elBounds = el.getBoundingClientRect()

                            const meta = el.getAttribute('ct:meta')

                            hoverEl = document.createElement('div')
                            hoverEl.classList.add('chart-tooltip')
                            hoverEl.innerHTML = meta
                            hoverEl.style.left = (elBounds.left - 10) + 'px'
                            hoverEl.style.top = (elBounds.top - 35) + 'px'
                            document.body.appendChild(hoverEl)
                        })

                        x.addEventListener('mouseout', e => {
                            const el = e.currentTarget
                            el.classList.remove('hovered')
                            if(hoverEl) hoverEl.remove()
                        })
                    })
                })
        </script>
        <?php
        return ob_get_clean();
    };

    $siteElId = 'site-' . randStr(12);

    ob_start();
    ?>
    <div class='site' id='<?= $siteElId; ?>'>
        <div class='site-summary'>
            <div class='details-toggle'></div>
            <div class='name'><a href='<?= $site['url']; ?>'><?= esc($site['name']); ?></a></div>
            <div class='last-checked'><?= esc($site['lastChecked']); ?></div>
            <div class='results'>
                <?= call(compose(
                    glue(''),
                    map($renderResult),
                    firstN($numResults)
                ), $site['results']); ?>
                <?= $renderResult($site['lastDownResult'], true); ?>
            </div>
        </div>
        <div class='details'>
            <?= $renderChart($site); ?>
        </div>
    </div>
    <script>
        (() => {
            const siteEl = document.querySelector('#<?= $siteElId; ?>')
            const toggleEl = siteEl.querySelector('.details-toggle')
            toggleEl.addEventListener('click', () => siteEl.classList.toggle('is-open'))
        })()
    </script>

    <?php

    return ob_get_clean();
});

$augResults = curry(function($db, $numResults, $site) {
    return setAt('results', $db->getResults($site['id'], $numResults), $site);
});

$augLastDown = curry(function($db, $site) {
    return setAt('lastDownResult', $db->getLastDownResult($site['id']), $site);
});

$db = new Db(getenv('DB_DSN'), getenv('DB_USER'), getenv('DB_PASS'));

$numResults = getAt('NUM_RESULTS', 20, $_ENV);
$numGraphResults = getAt('NUM_GRAPH_RESULTS', 100, $_ENV);

$sites = call(compose(
    map($augLastDown($db)),
    map($augResults($db, $numGraphResults))
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

        <link href="https://cdn.jsdelivr.net/chartist.js/latest/chartist.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/chartist.js/latest/chartist.min.js"></script>

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
                background: #222;
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

            h1 {
                color: #E33051;
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
                color: #6EAFBA;
            }

            a:hover {
                opacity: 0.8;
            }

            #content {
                width: 900px;
                max-width: 100%;
                margin: 0 auto;
                padding: 40px 60px;
                background: #2f2f2f;
                border-radius: 2px;
            }

            <?= $small; ?> {
                #content {
                    padding: 20px 20px;
                    border-radius: 0;
                }
            }

            #content > h1 {
                margin-top: 0;
            }

            .site {
            }

            <?= $small; ?> {
                .site {
                    margin-bottom: 40px;
                }
            }

            .site-summary {
                display: flex;
                align-items: center;
            }

            <?= $small; ?> {
                .site-summary {
                    display: block;
                }
            }

            .last-checked {
                padding-left: 15px;
                color: #E39922;
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

            .results .result.last-down {
                background: #fa868a;
                margin-left: 10px;
            }

            .results .result.last-down:before {
                content: '';
                display: block;
                position: absolute;
                width: 2px;
                height: 100%;
                background: #777;
                left: -8px;
            }

            .results .result .result-text {
                display: none;
                position: absolute;
                top: -28px;
                left: -12px;
                background: #8F7A6A;
                color: #E1DFDF;
                padding: 3px 5px;
                border-radius: 3px;
                font-size: 12px;
                z-index: 1;
                white-space: nowrap;
            }

            .results .result .result-text:after {
                content: '';
                display: block;
                position: absolute;
                left: 12px;
                bottom: -3px;
                background: #8F7A6A;
                width: 10px;
                height: 10px;
                transform: rotate(45deg);
                z-index: -1;
            }

            .results .result:hover .result-text {
                display: block;
            }

            .toggle-all,
            .details-toggle {
                cursor: pointer;
                user-select: none;
                padding: 10px 10px 10px 0;
            }

            .toggle-all {
                padding: 10px;
            }

            .toggle-all:after,
            .details-toggle:after {
                content: '';
                display: inline-block;
                border-right: 2px solid #555;
                border-bottom: 2px solid #555;
                transform: rotate(45deg);
                width: 8px;
                height: 8px;
                transition: all 300ms ease;
                position: relative;
                top: -2px;
            }

            .toggle-all.is-open:after,
            .site.is-open .details-toggle:after {
                transform: rotate(-135deg);
                top: 2px;
            }

            .details {
                transition: all 300ms ease;
                max-height: 0;
                overflow: hidden;
            }

            .site.is-open .details {
                max-height: 200px;
                padding-top: 10px;
            }

            .ct-grid {
                stroke: #444;
            }

            .ct-label {
                color: #777;
            }

            .ct-series-a .ct-line,
            .ct-series-a .ct-point {
                stroke: #E33051;
            }

            .ct-series-a .ct-point {
                stroke-width: 8px;
            }

            .ct-series-a .ct-line {
                stroke-width: 1px;
            }

            .chart-tooltip {
                position: fixed;
                z-index: 2;
                background: #8F7A6A;
                color: #E1DFDF;
                padding: 3px;
                border-radius: 3px;
                font-size: 12px;
                z-index: 1;
                padding: 3px 5px;
                white-space: nowrap;
            }

            .chart-tooltip:after {
                content: '';
                display: block;
                position: absolute;
                left: 5px;
                bottom: -3px;
                background: #8F7A6A;
                width: 10px;
                height: 10px;
                transform: rotate(45deg);
                z-index: -1;
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
            <h1>Health Check <span class='toggle-all'></span></h1>

            <script>
                (() => {
                    const toggleEl = document.querySelector('.toggle-all')
                    toggleEl.addEventListener('click', () => {
                        const isOpen = toggleEl.classList.contains('is-open')
                        if(isOpen) document.querySelectorAll('.site').forEach(x => x.classList.remove('is-open'))
                        else document.querySelectorAll('.site').forEach(x => x.classList.add('is-open'))
                        toggleEl.classList.toggle('is-open')                      
                    })
                })()
            </script>

            <?= call(compose(
                glue(''),
                map($renderSite($numResults))
            ), $sites); ?>
        </div>
    </body>
</html>
