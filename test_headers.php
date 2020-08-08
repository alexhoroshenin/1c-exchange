<?php


define("OPENCART_URL_RECEIVER", "http://s6.1c-shops.ru/tests/1c_exchange_test_receiver.php");

$image = file_get_contents("https://www.tvr.by/upload/iblock/42d/42d1898756e574fcaf9b7519c354ce9c.jpg");

$ContentType = 'Content-Type: image/jpg';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, OPENCART_URL_RECEIVER . "?filename=" . "myfile.jpg");
curl_setopt($ch, CURLOPT_TIMEOUT,20);
curl_setopt($ch, CURLOPT_POSTFIELDS, $image);
curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array($ContentType)); //доработка

$opencart_response_json = curl_exec($ch);

$response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE ); //Доработка

$opencart_response = json_decode($opencart_response_json);

echo 'Код ответа ' . $response_code;

echo "<br>";

echo curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

echo "<br>";

echo $opencart_response_json;

curl_close($ch);