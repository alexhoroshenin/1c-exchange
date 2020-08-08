<?php
//+генерация SEO url
//+Создание товара без картинки
//Созадание категорий
//Обновление товара и остатков

//Добавить в БД поле в oc_products id_1c UNIQUE


$json = '
    {
        "message": "hello",
        "login": "username700",
        "count": "2",
        "products": 
            [
                {
                    "product_id_1c": "7d6d6d5d-1898-11e4-bb59-000d884fd00d",
                    "group_id_1c": "60a1dd85-17d3-11e4-bb59-000d884fd00d", 
                    "name": "Название товара",
                    "description": "Описание товара",
                    "tag": "",
                    "meta_title": "Мета-тег Title",
                    "meta_description": "Мета-тег Description",
                    "meta_keyword": "",
                    "model": "model name",
                    "quantity": "100",
                    "price": "10",
                    "minimum": "1",
                    "stock_status_id": "7" 
                },
                {
                    "id_1c": "307a3bbe-1966-11e4-bb59-000d884fd00d",
                    "name": "Название товара",
                    "description": "Описание товара",
                    "tag": "",
                    "meta_title": "Мета-тег Title",
                    "meta_description": "Мета-тег Description",
                    "meta_keyword": "",
                    "model": "model name", 
                    "quantity": "100",
                    "price": "10",
                    "minimum": "1",
                    "stock_status_id": "7" 
                }
                
            ]
    }';


$decodeJSON = json_decode($json);

echo "<pre>";
print_r($decodeJSON);
echo "</pre>";

echo "<br><br><br><h1>Обработка в цикле</h1><br><br><br>";

foreach ($decodeJSON as $key => $value) {
    //обработка полей json

    if (!is_array($value)) {
        echo "{$key}: {$value}";
        echo "<br>";
    }

    if (is_array($value)) {
        //если это массив, то он содержит объекты с данными товаров
        foreach ($value as $obj => $obj_val) {
            //create_or_update_product()
            if (is_object($obj_val)) {
                foreach ($obj_val as $obj_field => $field_value) {
                    echo "$obj_field: $field_value <br>";
                }
            }
        }
    }
}


echo "<br><br><br><h1>Обращение напрямую к свойству</h1><br><br><br>";

echo $decodeJSON ->{'message'};

function translit_string($string_for_translit)
{
    $converter = array(
        'а' => 'a',    'б' => 'b',    'в' => 'v',    'г' => 'g',    'д' => 'd',
        'е' => 'e',    'ё' => 'yo',    'ж' => 'zh',   'з' => 'z',   'и' => 'i',
        'й' => 'y',    'к' => 'k',    'л' => 'l',    'м' => 'm',    'н' => 'n',
        'о' => 'o',    'п' => 'p',    'р' => 'r',    'с' => 's',    'т' => 't',
        'у' => 'u',    'ф' => 'f',    'х' => 'h',    'ц' => 'cz',   'ч' => 'ch',
        'ш' => 'sh',   'щ' => 'shh',  'ь' => '',     'ы' => 'y',    'ъ' => '',
        'э' => 'e',    'ю' => 'yu',   'я' => 'ya',
    );

    $string_for_translit = mb_strtolower($string_for_translit);
    $string_for_translit = strtr($string_for_translit, $converter);
    $string_for_translit = mb_ereg_replace('[^-0-9a-z]', '-', $string_for_translit);
    $string_for_translit = mb_ereg_replace('[-]+', '-', $string_for_translit);
    $string_for_translit = trim($string_for_translit, '-');

    return $string_for_translit;
}


echo "<br><br><br><h1>Генерация JSON из массива</h1><br><br><br>";

$array_to_json = array(
    "message" => "hello word",
    "message2" => "hello word2",
);

$output_json = json_encode($array_to_json);

echo $output_json;

