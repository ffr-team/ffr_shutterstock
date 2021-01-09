<?php
define('SHUTTERSTOCK_API_URL', 'https://api.shutterstock.com/v2/');
define('SHUTTERSTOCK_COLLECTION_URL', 'https://www.shutterstock.com/ru/collections/');

define('CHUNK_SIZE', 10);

define('JSON_TO_PHP', 'JSON_TO_PHP');
define('JSON_PRETTY', 'JSON_PRETTY');
define('RAW_RESULT', 'RAW_RESULT');

function apiGet($path, array $queryFields = null, $format = JSON_TO_PHP) {
    $search = empty($queryFields) ? '' : ('?' . http_build_query($queryFields));
    return curlQuery([
        CURLOPT_URL            => SHUTTERSTOCK_API_URL . $path . $search,
        CURLOPT_USERAGENT      => "php/curl",
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . API_TOKEN,
        ],
        CURLOPT_SSL_VERIFYHOST => false, //не проверять валидность сертификата владельца
        CURLOPT_SSL_VERIFYPEER => false, //не проверять валидность сертификата издателя
        CURLOPT_RETURNTRANSFER => 1,
    ], $format);
}

function apiPost($path, array $queryFields = null, array $body = null, $format = JSON_TO_PHP) {
    $search = empty($queryFields) ? '' : ('?' . http_build_query($queryFields));
    $encodedBody = empty($body) ? '' : json_encode($body);
    return curlQuery([
        CURLOPT_URL            => SHUTTERSTOCK_API_URL . $path . $search,
        CURLOPT_CUSTOMREQUEST  => "POST",
        CURLOPT_POSTFIELDS     => $encodedBody,
        CURLOPT_USERAGENT      => "php/curl",
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . API_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYHOST => false, //не проверять валидность сертификата владельца
        CURLOPT_SSL_VERIFYPEER => false, //не проверять валидность сертификата издателя
        CURLOPT_RETURNTRANSFER => 1,
    ], $format);
}

function getCollectionHtml($collectionId, $pageAt, $pageSize) {
    return curlQuery([
        CURLOPT_URL            => SHUTTERSTOCK_COLLECTION_URL . "{$collectionId}?page=$pageAt&perPage=$pageSize",
        CURLOPT_USERAGENT      => "php/curl",
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
    ], RAW_RESULT);
}

function loadChunk($collectionId, $loadFunc) {
    static $pageAt = 1;

    $response = getCollectionHtml($collectionId, $pageAt, CHUNK_SIZE);
    $pageAt++;

    $processed = 0;
    if (preg_match_all('/data-track="click\.userImageCollectionDetail\.viewImage"(.+?)<\/div>/', $response, $m)) {
        foreach ($m[1] as $str) {
            $imageId = preg_match_all('/name="(.+?)"/', $str, $m) ? $m[1][0] : null;
            $description = preg_match_all('/alt="(.+?)"/', $str, $m) ? $m[1][0] : null;
            $thumb = preg_match_all('/src="(.+?)"/', $str, $m) ? $m[1][0] : null;
            $loadFunc($imageId, $description, $thumb);
            $processed++;
        }
    }
    return $processed >= CHUNK_SIZE;
}

function downloadFile($url, $toFileName) {
    $in = fopen($url, 'r');
    if (empty($in)) {
        die("ERROR: downloadFile.fopen($url)");
    }
    $out = fopen($toFileName, 'w');
    if (empty($out)) {
        die("ERROR: downloadFile.fopen($toFileName)");
    }
    $size = stream_copy_to_stream($in, $out);
    if (empty($size)) {
        die("ERROR: downloadFile.stream_copy_to_stream");
    }
    fclose($in);
    fclose($out);
    return $size;
}

function curlQuery($options, $format = JSON_TO_PHP) {
    $handle = curl_init();
    if (!$handle) {
        die('ERROR: curl_init');
    }

    curl_setopt_array($handle, $options);
    $response = curl_exec($handle);
    if (!$response) {
        die('ERROR: curl_exec: ' . curl_error($handle));
    }
    lastStatusCode(curl_getinfo($handle, CURLINFO_HTTP_CODE));
    curl_close($handle);

    switch ($format) {
    case JSON_PRETTY:
        return jsonPretty($response);
    case JSON_TO_PHP:
        return json_decode($response, true);
    }
    return $response; //'RAW_RESULT
}

function lastStatusCode($setStatusCode = null) {
    static $lastStatusCode;
    if ($setStatusCode !== null) {
        $lastStatusCode = $setStatusCode;
    }
    return $lastStatusCode;
}

function jsonPretty($json) {
    $result = is_string($json) ? json_decode($json, true) : $json;
    return empty($result) ? '' : json_encode($result, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES);
}

/**
 * Прочитать данные подписки
 * @return array [$subscriptionId, $downloadsLeft]
 */
function readSubscription() {
    $subscriptions = apiGet('user/subscriptions');
    $statusCode = lastStatusCode();
    if (!isset($subscriptions['data'][0]['id'], $subscriptions['data'][0]['allotment']['downloads_left'])) {
        die("\nERROR: reading or parsing subscription failed (StatusCode: $statusCode). Answer:\n" . jsonPretty($subscriptions));
    }
    return [
        $subscriptions['data'][0]['id'],
        $subscriptions['data'][0]['allotment']['downloads_left'],
    ];
}
