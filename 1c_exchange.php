<?php
/*Скрипт обрабатывает запросы из 1С
Преобразовывает XML-документ в json
Сохраняет кратинки на сервер
Отправляет данные через API запрос в opencart
*/

// Функции обработки запросов от 1С
function process_request_1c($mode)
{
    if($mode == "checkauth"){
        process_checkauth_request();
    }elseif ($mode == "init"){
        process_init_request();
    }elseif ($mode == "file"){
        process_file_request();
    }else{
        echo 'success';
    }

}

function process_checkauth_request()
{
    echo 'success';
    echo PHP_EOL;
    echo 'auth';
    echo PHP_EOL;
    echo 'auth:123456';

}

function process_init_request()
{
    echo ("zip=no");
    echo PHP_EOL;
    echo ("file_limit=10240000");

}

function process_file_request()
{
    $file_open = file_get_contents('php://input');
    $filename = $_POST['filename'];

    $path_to_save_xml = create_or_get_directory(PATH_TO_XML);
    $path_to_save_image = create_or_get_directory(PATH_TO_IMAGE);


    if (!$file_open) {
        add_to_log("Файл Не открылся " . $filename);
        echo 'not success';

        return false;
    }

    add_to_log("Открыли файл " . $filename);

    $file_extension = get_filename_last_part($filename, '.');

    if ($file_extension == 'xml') {

        save_file($path_to_save_xml, $filename, $file_open);

    } else {

        $filename = get_filename_last_part($filename, '/');
        add_to_log("загружаем картинку " . $filename);
        save_file($path_to_save_image, $filename, $file_open);

    }

    echo 'success';

    return true;

}

// Функции работы с файлами
function get_filename_last_part($file_name, $delimeter){

    $pieces = explode($delimeter, $file_name);

    return end($pieces);
}

function save_file($path, $filename, $file)
{
    $saved_file = file_put_contents($path . $filename, $file);

    if ($saved_file) {
        add_to_log("Файл сохранен " . $path . $filename);

        return true;
    }else{
        add_to_log("Файл НЕ сохранен " . $path . $filename);

        return false;
    }
}

function create_or_get_directory($folder_name){

    $path = __DIR__ . "/" . $folder_name . "/";

    if (!file_exists($path)) {
        mkdir($path, 0777, true);
        add_to_log("Создана дирректория " . $path);
    }

    return $path;
}

function drop_sended_file($filename){

    $deleted_file = unlink($filename);

    if ($deleted_file){
        add_to_log("Удален файл ". $filename);
    } else {
        add_to_log("При удалении файла произошла ошибка ". $filename);
    }
}


// Функции декодирования XML в объект
function decode_xml($xml_file){

    $decoded_xml = decode_xml_to_object($xml_file);

    if ($decoded_xml === false){
        add_to_log("Ошибка: сбой при загрузке файла $xml_file");
    }

    return $decoded_xml;
}

// Функции извлечения информации о товарвх и остатках из XML
function serialize_data($xml_file){

    $xml = decode_xml_to_object($xml_file);

    if (isset($xml->Каталог->Товары)){
        $response_array = serialize_products($xml);

        //Если категории находятся в Товар->ЗначенияРеквизитов->ВидНоменклатуры, тогда
        //проходимся циклом по массиву товаров и копируем категории из товаров
        $response_array = serialize_categories($response_array);

    } elseif (isset($xml->ПакетПредложений)){

        $response_array = serialize_stock_balance($xml);

    } else{
        add_to_log('Ошибка: Тип XML-документа не опознан');
        return false;
    }

    return $response_array;

}

function serialize_products($xml){

    $products = $xml->Каталог->Товары;

    add_to_log('Старт сериализации товаров');

    $products_to_response = array();

    foreach ($products->Товар as $product){

        $current_product = array(
            "product_id_1c" => (string)$product->Ид,
            "model" => (string)$product->Артикул,
            "name" => (string)$product->Наименование,
            "description" => (string)$product->Описание,
            "meta_description" => (string)$product->Описание,
            "meta_title"  => (string)$product->Наименование,
            "minimum" => "1",
            "stock_status_id"=> "7"
        );

        if(isset($product->ЗначенияРеквизитов)){
            foreach ($product->ЗначенияРеквизитов->ЗначениеРеквизита as $value){
                if($value->Наименование == "ВидНоменклатуры"){
                    $current_product["group_id_1c"] = (string)$value->Значение;
                }
            }
        }
        $products_to_response[] = $current_product;
        unset($current_product);

    }

    add_to_log("Сериализовано товаров: " . count($products_to_response));

    $response_array['products'] = $products_to_response;

    return $response_array;

}

function serialize_categories($response){
    add_to_log('Старт сериализации категорий');

    $categories_to_response = array();

    foreach ($response['products'] as $product){

        if(isset($product['group_id_1c']) && !in_array($product['group_id_1c'], $categories_to_response)){
            $categories_to_response[] = $product['group_id_1c'];
        }
    }

    $response['categories'] = $categories_to_response;

    add_to_log("Загружено категорий: " . count($categories_to_response));

    return $response;
}

function serialize_stock_balance($xml)
{
    add_to_log('Старт загрузки остатков товаров');

    $offers = $xml->ПакетПредложений->Предложения;

    $response = array();
    $stock_balance = array();

    foreach ($offers->Предложение as $offer){

        $id = clear_id($offer->Ид);
        $price = (double)$offer->Цены->Цена->ЦенаЗаЕдиницу;
        $balance = (string)$offer->Количество;

        $balance_info = array(
            "price" => $price,
            "balance" => $balance
        );

        $stock_balance[$id] = $balance_info;

    }

    $response["stock_balance"] = $stock_balance;
    add_to_log("Загружены остаки для номенклатуры (шт): " . count($stock_balance));

    return $response;

}

function decode_xml_to_object($xml){
    /* Возвращает файл в виде объекта или false в случае ошибки */

    return simplexml_load_file($xml);
}

//Функции отправки JSON в opencart
function send_data_to_opencart_api(){

    $path_xml = create_or_get_directory(PATH_TO_XML);
    $path_images = create_or_get_directory(PATH_TO_IMAGE);

    $xml_files = scandir($path_xml);
    $image_files = scandir($path_images);

    $opencart_response = false;

    if(count($xml_files) > 0){
        foreach($xml_files as $key=>$xml_filename){

            $path_to_file = $path_xml . $xml_filename;
            $data = serialize_data($path_to_file);
            $json = json_encode($data);

            $opencart_response = send_json_post_request_to_opencart_api($json, $path_to_file);

            //Удаление файла
            drop_sended_file($path_to_file);
        }
    }

    if(count($image_files) > 0){
        foreach($image_files as $key=>$image){

            $path_to_file = $path_xml . $image;

            $opencart_response = send_image_post_request_to_opencart_api($image, $path_to_file);

            //Удаление файла
            drop_sended_file($path_to_file);
        }
    }

    return $opencart_response;
}

function send_json_post_request_to_opencart_api($json, $filename){

    add_to_log('Отправлен файл в opencart api ' . $filename);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, OPENCART_URL_RECEIVER);
    curl_setopt($ch, CURLOPT_TIMEOUT,10);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

    $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE );

    $opencart_response_json = curl_exec($ch);

    curl_close($ch);

    $opencart_response = json_decode($opencart_response_json);


    add_to_log("Ответ сервера opencart " . $response_code);
    add_to_log(json_decode($opencart_response));

    return $opencart_response;
}

function send_image_post_request_to_opencart_api($file_name, $file_path){

    add_to_log('Отправлен файл в opencart api ' . $file_path);

    $image = file_get_contents($file_path);
    $image_extension = get_filename_last_part($file_name, '.');
    $ContentType = 'Content-Type: image/' . $image_extension;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, OPENCART_URL_RECEIVER . "?filename=" . $file_name);
    curl_setopt($ch, CURLOPT_TIMEOUT,20);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $image);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $ContentType);

    $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE );

    $opencart_response_json = curl_exec($ch);

    curl_close($ch);

    $opencart_response = json_decode($opencart_response_json);


    add_to_log("Ответ сервера opencart " . $response_code);
    add_to_log(json_decode($opencart_response));

    return $opencart_response;

}

// Вспомогательные функции
function clear_id($id){
    //Неокторые ИД в offer.xml имеют добавочную часть, которую мы отбрасываем
    //делим ИД по разделителю # и берем первую часть

    $id_array = explode ('#', $id);

    return $id_array[0];

}


/*    MAIN    */


define("PATH_TO_XML", "import_1c_xml");
define("PATH_TO_IMAGE", "import_1c_image");
define("OPENCART_URL_RECEIVER", "http://s6.1c-shops.ru/1c_exchange_test.php");

require_once('1c_exchange_log.php');

add_to_log($_SERVER['REQUEST_METHOD'] . " " . $_SERVER['QUERY_STRING']);

if ($_GET['mode'] || $_POST['mode']){

    if($_GET['mode']){
        $mode = $_GET['mode'];
    } else{
        $mode = $_POST['mode'];
    }

    //Обрабатываем запрос от 1С
    //Сохраняем файлы XML или картинки на сервер по одному на запрос
    process_request_1c($mode);

    //Отправляем полученный файл в opencart API
    //Если это картинка, отправляем ссылку на неё
    //Если это XML-файл, сериализуем его и отправляем jSON
    //Если файлы в папках не появились, то не отправляем ничего
    $message = send_data_to_opencart_api();


}
