<?php

/**
 * В lib/defines.php задаются необходимые входные данные (аккаунт, токен, идентификатор коллекции).
 * В lib/lib.php - библиотека функций.
 */
require_once __DIR__ . '/defines.php';
require_once __DIR__ . '/lib.php';

/**
 * Чтение данных подписки (лицензии).
 * Определение идентификатора подписки, который нужен для лицензирования изображений.
 * Определение остатка количества разрешенных загрузок.
 */
echo "Reading a subscription";
list($subscriptionId, $downloadsLeft) = readSubscription();
echo ". Downloads left: $downloadsLeft\n";

/**
 * Чтение HTML-страниц коллекции по заданному COLLECTION_ID.
 * Парсинг HTML для получения идентификаторов и описаний выбранных изображений.
 */
echo "Reading the collection " . COLLECTION_ID;
$images = [];
while (loadChunk(COLLECTION_ID, function ($imageId, $description, $thumb) use (&$images) {
    $images[$imageId] = [$description, $thumb];
}));
if (empty($images)) {
    die("\nERROR: reading collection failed");
}
$imagesCount = count($images);
if ($imagesCount > $downloadsLeft) {
    die("\nERROR: The number of images in the collection $imagesCount exceeds the number in the subscription $downloadsLeft.");
}
echo ". Images count: $imagesCount\n";

/**
 * Лицензирование выбранных изображений.
 * Получение ссылок на загрузку изображений.
 * ОСТОРОЖНО! На этом этапе вычитается количество из остатка разрешенных загрузок.
 * Лицензируем по одному изображению в запросе, т.к. не все лицензии допускают множественное лицензирование.
 */
echo "Image licensing";
$licenses = [];
$errorsCount = $licensesCount = 0;
foreach ($images as $imageId => list($description, $thumb)) {
    $answer = apiPost(
        'images/licenses',
        ['subscription_id' => $subscriptionId],
        ['images' => [
            [
                'image_id' => "$imageId",
                'price'    => 0,
                'metadata' => ['customer_id' => ''],
            ],
        ]]
    );
    if (!isset($answer['data'][0]['download']['url'], $answer['data'][0]['image_id'])) {
        $errorsCount++;
        $licenses[$imageId] = [
            'error' => isset($answer['data'][0]['error']) ? $answer['data'][0]['error'] : 'no error description',
        ];
    } else {
        $licensesCount++;
        $licenses[$imageId] = [
            'download_url'     => $answer['data'][0]['download']['url'],
            'allotment_charge' => $answer['data'][0]['allotment_charge'],
            'description'      => $description,
            'thumb'            => $thumb,
        ];
    }
}
echo ". Success: $licensesCount" . ($errorsCount > 0 ? ", Errors: $errorsCount" : '') . "\n";

/**
 * Запись результатов лицензирования в файл <COLLECTION_ID>.txt
 */
$outFileName = COLLECTION_ID . '.txt';
echo "Save results to " . $outFileName;
$output = '';
foreach ($licenses as $imageId => $license) {
    $output .= "image_id: $imageId\n";
    foreach ($license as $key => $value) {
        $output .= "$key: $value\n";
    }
    $output .= "\n";
}
file_put_contents($outFileName, $output);
echo "\n";

/**
 * Загрузка изображений по ссылкам, полученным при лицензировании
 * Изображения записываются в файлы <ImageId>.jpg
 */
echo "Downloads images\n";
foreach ($licenses as $imageId => $license) {
    if (isset($license['error'])) {
        continue;
    }
    $toFileName = basename($license['download_url']);
    echo $toFileName;
    $size = downloadFile($license['download_url'], $toFileName);
    echo " - size: $size\n";
}

/**
 * Чтение данных подписки (лицензии), чтобы показать остатка количества разрешенных загрузок.
 */
echo "Downloads left: ";
list(, $downloadsLeft) = readSubscription();
echo $downloadsLeft . "\n";
