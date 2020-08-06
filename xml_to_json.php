<?php

// Функции декодирования XML в объект
function decode_xml($xml_file){

    $decoded_xml = decode_xml_to_object($xml_file);

    if ($decoded_xml === false){
        add_to_log("Ошибка: сбой при загрузке файла $xml_file");
    }

    return $decoded_xml;
}

// Функции извлечения информации о товарвх и остатках из XML
function serialize_data($xml){

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

// Вспомогательные функции
function clear_id($id){
    //Неокторые ИД в offer.xml имеют добавочную часть, которую мы отбрасываем
    //делим ИД по разделителю # и берем первую часть

    $id_array = explode ('#', $id);

    return $id_array[0];

}

/*   MAIN   */

require_once('1c_exchange_log.php');


$xml_file = "C:\Users\Алексей\Desktop\Товары_full\webdata\offers0_1.xml";

//$xml_file = "C:\Users\Алексей\Desktop\Товары_full\webdata\import0_1.xml";

$decoded_xml = decode_xml($xml_file);

if (!$decoded_xml){
    exit();
}

$response_array = serialize_data($decoded_xml);

echo "<br><br><h1>Выходной массив</h1><br><br>";

echo '<pre>';
print_r($response_array);
echo '</pre>';

$encode_response = json_encode($response_array);

echo "<br><br><h1>Выходной JSON объект 2</h1><br><br>";
print_r(json_encode($encode_response));

echo "<br><br><h1>Выходной JSON объект 3</h1><br><br>";
echo "<pre>";
print_r(json_decode($encode_response, true));
echo "</pre>";

