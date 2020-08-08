<?php

$array_to_send = array(
  "foo" => "bar",
  "number" => "one",

);

$json = json_encode($array_to_send);

$image = file_get_contents('http://s6.1c-shops.ru/image/cache/catalog/demo/banners/iPhone6-1140x380.jpg');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://s6.1c-shops.ru/1c_exchange_test_receiver.php?file_name=image.jpg');
curl_setopt($ch, CURLOPT_TIMEOUT,10);
curl_setopt($ch, CURLOPT_HEADER,0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);

//curl_setopt($ch, CURLOPT_POSTFIELDS, $json );
curl_setopt($ch, CURLOPT_POSTFIELDS, $image );

//curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: image/jpg'));


$out = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE );
curl_close($ch);

print_r(json_decode($out));
echo $code;



//curl_setopt($ch, CURLOPT_VERBOSE  , true);
//curl_setopt($ch, CURLOPT_POSTFIELDS, '{"message":{"hello":"world","i am":"work"}}');
//curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

//curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
//curl_setopt($ch, CURLOPT_STDERR, $verbose);



/*
$curlOptions = array(
    CURLOPT_TIMEOUT => 10,
    CURLOPT_POSTFIELDS => '{"message":{"hello":"world","i am":"work"}}',

);

$url = "http://s6.1c-shops.ru/test_curl_receiver.php?file_name=image.jpg";
$handle = curl_init($url);
curl_setopt_array($handle, $curlOptions);
$content = curl_exec($handle);
//echo "Verbose information:\n", !rewind($verbose), stream_get_contents($verbose), "\n";
curl_close($handle);
echo $content;