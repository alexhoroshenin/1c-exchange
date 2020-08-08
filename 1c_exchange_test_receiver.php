<?php

require_once ("1c_exchange_log.php");

$json123 = '{"message":{"result":"success"}}';

add_to_log("->>>>> OPENCART " . $_SERVER['QUERY_STRING']);
add_to_log("->>>>> OPENCART " . $_SERVER['CONTENT_TYPE']);


if ($_SERVER['REQUEST_METHOD'] = "POST"){


    if ($_SERVER["CONTENT_TYPE"] == "application/json"){

        $postData = file_get_contents('php://input');
        $json = json_decode($postData);

        $count_products = count($json->products);
        $count_categories = count($json->categories);


        $response = array(
            "count_products" => $count_products,
            "count_categories" => $count_categories,

        );

        $json_response = json_encode($response);

        header('Content-Type: application/json');
        echo $json123;

        add_to_log("->>>>> OPENCART что отправил" . $json123); //отладка

    }

    if (strstr($_SERVER["CONTENT_TYPE"], "image")){



        add_to_log("->>>>> OPENCART " . "Получили запрос с картинкой");

        $postData = file_get_contents('php://input');

        if ($postData){

            add_to_log("->>>>> OPENCART " . "Открыли картинку");

            if ($_GET['filename']){
                $filename = $_GET['filename'];
            } else {
                $filename = 'abra.jpg';
            }

            $saved_file = file_put_contents( __DIR__ . "/" . $filename, $postData);

            if($saved_file){
                echo '{"message":"saved file"' . $filename . '}';
                add_to_log("->>>>> OPENCART " . 'Сохранили картинку');

                $response = array(
                    "message" => "saved file",
                    "filename" => $filename,
                );

                $json_response = json_encode($response);

                header('Content-Type: application/json');

                echo $json_response;

                add_to_log("->>>>> OPENCART количество отправленных строк" . count( json_decode($json_response))); //отладка

            }

        } else {
            add_to_log("->>>>> OPENCART " . 'Не получили картинку');
            echo '{"message":"no post data"}';
        }
    }

}

