<?php

require __DIR__ . '/../src/RollingCurl/RollingCurl.php';
require __DIR__ . '/../src/RollingCurl/Request.php';

$rollingCurl = new \RollingCurl\RollingCurl();

for ($i = 0; $i <= 500; $i+=10) {
    // https://www.google.com/search?q=curl&start=10
    $rollingCurl->get('https://www.google.com/search?q=curl&start=' . $i);
}

$results = array();

$start = microtime(true);
echo "Fetching..." . PHP_EOL;
$rollingCurl
    ->setCallback(function(\RollingCurl\Request $request, \RollingCurl\RollingCurl $rollingCurl) use (&$results) {
        if ($request->getResponseInfo()->http_code==200 && preg_match_all('#<h3 class="r"><a href="([^"]+)">(.*)</a></h3>#iU', $request->getResponseText(), $out)) {
            foreach ($out[1] as $idx => $url) {
                parse_str(parse_url($url, PHP_URL_QUERY), $params);
                if (isset($params['q'])) {
                    $results[$params['q']] = strip_tags($out[2][$idx]);
                }
            }
        }
        echo "Fetch complete for (" . $request->getUrl() . ")" . PHP_EOL;
    })
    ->setSimultaneousLimit(10)
    ->execute();
;
echo "...done in " . (microtime(true) - $start) . PHP_EOL;

echo "All results: " . PHP_EOL;
print_r($results);