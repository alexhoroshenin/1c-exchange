<?php
/*
В таблице товаров важно создать ограничение на уникальность поля МОДЕЛЬ, чтобы не дублировать товары
1. ALTER TABLE oc_product

ALTER TABLE oc_product ADD COLUMN id_1c VARCHAR(100) UNIQUE;

ALTER TABLE oc_product ADD UNIQUE (id_1c);

ADD COLUMN id_1c VARCHAR(100) UNIQUE;

$this->db->query("ALTER TABLE " . DB_PREFIX . "product ADD UNIQUE (model)");

ИЛИ до устанновки и распаковки в файлу opencart.sql добавить строку в место, где создаетсф таблица oc_product

ВОзможно будет ошибка в пункте 1: Invalid default value for 'date_available'
Для этого установим значение по умолчанию для date_available
ALTER TABLE oc_product CHANGE COLUMN date_available date_available DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
или вариант 2
`date_available` date NOT NULL DEFAULT '0000-00-00',

*/


class ControllerApiExchange1c extends Controller
{
    public $response_body = array();

    public function index()
    {

        $this->add_to_log('Старт тест #1');


    }

    public function load_data()
    {

        $this->add_to_log('Старт функции load_data');

        if ($_SERVER["CONTENT_TYPE"] == "application/json" && $_SERVER['REQUEST_METHOD'] == "POST") {
            $this->load_json();

        } elseif (strstr($_SERVER["CONTENT_TYPE"], "image") && $_SERVER['REQUEST_METHOD'] == "POST") {
            $this->load_image();

        }

        $this->response_body['type'] = $_SERVER["CONTENT_TYPE"]; // отладка
        $this->send_response_message();


        /*
        foreach ($decode_input as $key => $value) {
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


        $data = array(
            'model' => rand(100, 1000),
            'quantity' => 10,
            'price' => 100,
            'minimum' => 1,
            'stock_status_id' => '7', //"7">В наличии  5">Нет в наличии  6">Ожидание 2-3 дня   8">Предзаказ

        );

        $product_description = array(
            'language_id' => '1',
            'name' => 'Название товара',
            'description' => '&lt;p&gt;Описание товара&lt;/p&gt;',
            'tag' => '',
            'meta_title' => 'Мета-тег Title',
            'meta_description' => 'Мета-тег Description',
            'meta_keyword' => ''
        );

        $data['product_description'] = $product_description;

        try {
            $product_id = $this->model_catalog_product->addProduct($data);
        } catch (Exception $e) {
            $product_id = $e->getMessage();
        }

*/


    }

    private function send_response_message()
    {
        $response_json = json_encode($this->response_body);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput($response_json);
    }

    private function load_json()
    {

        $this->add_to_log('Старт функции load_json');

        $input_json = file_get_contents('php://input');
        $decode_input = json_decode($input_json, true);

        if (isset($decode_input['categories'])) {
            $this->load_categories($decode_input['categories']);
        }

        if (isset($decode_input['products'])) {
            $this->load_products($decode_input['products']);
        }

        if (isset($decode_input['stock_balance'])) {
            $this->load_stock_balance($decode_input['stock_balance']);
        }
    }

    private function load_categories($categories_from_json)
    {

        $this->add_to_log('Старт функции load_categories');

        $exists_categories = $this->get_exists_categories();

        foreach ($categories_from_json as $category_name) {

            $it_exist = in_array($category_name, $exists_categories);

            if (!$it_exist) {
                $this->add_to_log('Категория ' . $category_name . 'НЕ существует');
                $this->create_category($category_name);
            }
            $this->add_to_log('Категория ' . $category_name . 'Существует');
        }

    }

    private function create_category($name)
    {

        $this->add_to_log('Старт функции create_category');

        $sql_category = "INSERT INTO " . DB_PREFIX . "category SET " .
            "parent_id = 0" .
            ", `top` = 1" .
            ", `column` = 1" .
            ", sort_order = 0" .
            ", status = 1" .
            ", date_modified = NOW(), date_added = NOW()";

        $this->db->query($sql_category);

        $this->add_to_log('Запрос 1 в функции create_category выполен');

        $category_id = $this->db->getLastId();

        $sql_category_description = "INSERT INTO " . DB_PREFIX . "category_description SET " .
            "category_id = " . (int)$category_id .
            ", language_id = 1" .
            ", name = '" . $this->db->escape($name) .
            "', meta_title =  '" . $this->db->escape($name) . "'";

        $this->db->query($sql_category_description);

        $this->add_to_log('Запрос 2 в функции create_category выполен');

        // MySQL Hierarchical Data Closure Table Pattern
        $level = 0;

        $sql_category_path = "INSERT INTO `" . DB_PREFIX . "category_path` SET `category_id` = '" . (int)$category_id .
            "', `path_id` = '" . (int)$category_id . "', `level` = '" . (int)$level . "'";

        $this->db->query($sql_category_path);

        $this->add_to_log('Запрос 3 в функции create_category выполен');

        //SEO url

        $seo_url = $this->generate_seo_url($name);

        $sql_seo_url = "INSERT INTO " . DB_PREFIX . "seo_url SET store_id = 0" .
            ", language_id = 1" . ", query = 'category_id=" . (int)$category_id .
            "', keyword = '" . $this->db->escape($seo_url) . "'";

        $this->db->query($sql_seo_url);

        $this->add_to_log('Запрос 4 в функции create_category выполен');

        $sql_category_to_shop = "INSERT INTO " . DB_PREFIX . "category_to_store SET store_id = 0" .
            ", category_id=" . (int)$category_id;


        $this->add_to_log($sql_category_to_shop);

        $this->db->query($sql_category_to_shop);

        $this->add_to_log('Запрос 5 в функции create_category выполен');


        $this->add_to_log('Функция завершила выполнение');

    }

    private function load_image()
    {
        $input_image = file_get_contents('php://input');
        $image_name = $_GET['filename'];

    }

    private function load_products($products)
    {

        $this->add_to_log('Старт функции load_products');

        $this->load->model('catalog/ex1cproduct');
        $exists_products_id_1c = $this->model_catalog_ex1cproduct->get_products_id_1c();
        $exists_categories = $this->get_exists_categories();

        foreach ($products as $product_from_json) {
            $id_1c_from_json = $product_from_json['product_id_1c'];

            $it_exists = in_array($id_1c_from_json, $exists_products_id_1c);

            if ($it_exists) {
                $this->add_to_log('Обновляем продукт ' . $id_1c_from_json);
                $id_of_exists_product = $this->get_key_by_value_from_array($id_1c_from_json, $exists_products_id_1c);
                $this->update_product($product_from_json, $id_of_exists_product);
            } else {
                $this->add_to_log('Создаем продукт ' . $id_1c_from_json);
                $this->create_product($product_from_json, $exists_categories);
            }
        }

    }

    private function get_key_by_value_from_array($key, $arr)
    {
        return array_search($key, $arr);
    }


    private function update_product($product, $exists_categories)
    {

        $this->add_to_log('Старт функции update_product');

        $id_1c = $product['product_id_1c'];

        //Получаем описание товара
        $sql_product =
            "SELECT p.product_id, p.model, p.stock_status_id, p.minimum, d.name, d.meta_title" .
            " FROM oc_product AS p" .
            " LEFT JOIN oc_product_description AS d ON p.product_id = d.product_id" .
            " WHERE p.id_1c = '" . $this->db->escape($id_1c) . "'";

        $query = $this->db->query($sql_product);

        $this->add_to_log('Запрос 1 в функции update_product выполнен');

        $id_opencart = $query->row['product_id'];

        $this->add_to_log('$id_opencart ' . $id_opencart);

        $is_equal_description = $this->compare_product_description($product, $query->row);

        if (!$is_equal_description) {
            $this->change_product_description($id_opencart, $product);
        }

        //Сравнение категории в opencart и 1с.
        //В опенкарт может быть больше одной категории

        $sql_categories =
            "SELECT cd.category_id, cd.name " .
            " FROM " . DB_PREFIX . "category_description AS cd" .
            " INNER JOIN " . DB_PREFIX . "product_to_category AS pc" .
            " ON cd.category_id = pc.category_id" .
            " WHERE pc.product_id = " . $id_opencart;

        $this->add_to_log($sql_categories);

        $query = $this->db->query($sql_categories);

        $this->add_to_log('Запрос 2 в функции update_product выполнен');

        $isset_category = $this->this_category_isset_in_product($product, $query->rows);

        if (!$isset_category) {
            $this->change_product_category($id_opencart, $product, $query->rows);
        }

    }

    private function create_product($product, $exists_categories)
    {

        $this->add_to_log('Старт функции create_product');

        if (isset($product['group_id_1c'])) {
            $group_id_1c = $product['group_id_1c'];
            $category = array_search($group_id_1c, $exists_categories);
        }

        $sql_product = "INSERT INTO " . DB_PREFIX . "product SET " .
            " model = '" . $product['model'] . "'" .
            ", stock_status_id =" . (int)($product['stock_status_id']) .
            ", id_1c = '" . $this->db->escape($product['product_id_1c']) .
            "', quantity = 1" .
            ", minimum = 1" .
            ", price = 1" .
            ", status = 1" .
            ", date_added = NOW(), date_modified = NOW()";

        $this->db->query($sql_product);

        $this->add_to_log('Запрос 1 в функции create_product выполнен');

        $product_id = $this->db->getLastId();

        $seo_url = $this->generate_seo_url($product['name'], $product_id);

        $sql_product_description = "INSERT INTO " . DB_PREFIX . "product_description SET" .
            " product_id = '" . (int)$product_id .
            "', language_id = 1" .
            ", name = '" . $this->db->escape($product['name']) .
            "', description = '" . $this->db->escape($product['description']) .
            "', tag = '" .
            "', meta_title = '" . $this->db->escape($product['name']) .
            "', meta_description = '" . $this->db->escape($product['meta_description']) .
            "', meta_keyword ='' ";

        $this->db->query($sql_product_description);

        $this->add_to_log('Запрос 2 в функции create_product выполнен');

        $sql_seo_url = "INSERT INTO " . DB_PREFIX . "seo_url SET store_id = 0" .
            ", language_id = 1" . ", query = 'product_id=" . (int)$product_id .
            "', keyword = '" . $this->db->escape($seo_url) . "'";

        $this->add_to_log($sql_seo_url);

        $this->db->query($sql_seo_url);

        $this->add_to_log('Запрос 3 в функции create_product выполнен');

        $sql_product_to_store = "INSERT INTO " . DB_PREFIX . "product_to_store SET store_id = 0"
        .", product_id=" . (int)$product_id;

        $this->db->query($sql_product_to_store);

        $this->add_to_log('Запрос 4 в функции create_product выполнен');

        if (isset($category)){
            $this->add_to_log('Категория ' . $category);
            $sql_product_to_category = "INSERT INTO " . DB_PREFIX . "product_to_category SET product_id = " .
                (int)$product_id . ", category_id = " . (int)$category;

            $this->db->query($sql_product_to_category);

            $this->add_to_log('Запрос 5 в функции create_product выполнен');
        }

        $this->cache->delete('product');

        $this->add_to_log('Функция create_product завершила работу');

    }

    private function compare_product_description($product_from_json, $product_from_opencart)
    {
        $this->add_to_log('Старт функции compare_product_description');

        // поля в $product_from_opencart
        // p.product_id, p.model, p.stock_status_id, p.minimum, d.name, d.meta_title

        if ($product_from_opencart['model'] == $product_from_json['model'] &&
            $product_from_opencart['stock_status_id'] == $product_from_json['stock_status_id'] &&
            $product_from_opencart['minimum'] == $product_from_json['minimum'] &&
            $product_from_opencart['name'] == $product_from_json['name']) {

            return true;
        }

        return false;
    }

    private function change_product_description($id_opencart, $product)
    {

        $this->add_to_log('Старт функции change_product_description выполнен');

        $sql_product_change_model = "UPDATE " . DB_PREFIX . "product SET model = '" .
            $this->db->escape($product['model']) .
            "', stock_status_id = '" . (integer)$product['stock_status_id'] .
            "', minimum = " . (integer)$product['minimum'] .
            " WHERE product_id = '" . $this->db->escape($id_opencart) . "'";

        $this->db->query($sql_product_change_model);

        $this->add_to_log('Запрос 1 в функции change_product_description выполнен');

        $sql_product_change_description = "UPDATE " . DB_PREFIX . "product_description SET " .
            "name = '" . $this->db->escape($product['name']) .
            "', description = '" . $this->db->escape($product['description']) .
            "', meta_description = '" . $this->db->escape($product['meta_description']) .
            "', meta_title = '" . $this->db->escape($product['name']) .
            "' WHERE product_id = '" . $id_opencart . "'";


        $this->add_to_log($sql_product_change_description);

        $this->db->query($sql_product_change_description);

        $this->add_to_log('Запрос 2 в функции change_product_description выполнен');

    }

    private function this_category_isset_in_product($product, $categories_from_query)
    {

        if (!isset($product['$category'])) {
            return true;
        }

        foreach ($categories_from_query as $category) {
            if ($category['name'] == $product['$category']) {
                return true;
            }
        }
        return false;
    }

    private function change_product_category($id_opencart_product, $product_from_json, $categories_from_query)
    {

        $this->add_to_log('Старт функции change_product_category');

        $category_from_json = $product_from_json['category'];

        foreach ($categories_from_query as $category) {
            if ($category['name'] == $category_from_json) {

                $category_id = $category['category_id'];
                //нужная категория найдена в списке категорий, добавим ее в продукт

                $sql_set_category = "INSERT INTO " . DB_PREFIX . "product_to_category SET " .
                    "product_id = " . (integer)$id_opencart_product .
                    ", category_id = " . (integer)$category_id;

                $this->db->query($sql_set_category);

                $this->add_to_log('Запрос в  функции change_product_category выполнен');

            }
        }

    }


    private function load_stock_balance($stock_balance)
    {

        /*Для обновления остатков и цен в одном запросе (не в цикле) используется запрос вида
        UPDATE oc_product
        SET price = (CASE
                WHEN id_1c = 'XXX' THEN 100
                WHEN id_1c = 'YYY' THEN 200
                ELSE price
                END),

            quantity = (CASE
                WHEN id_1c = 'XXX' THEN 10
                WHEN id_1c = 'YYY' THEN 20
                ELSE quantity
                END)
        */

        //Сначала получаем список всех товаров из opencart
        //Сравниваем со списком из json товары у откорых есть id_1c
        //Обновляем информацию для тех товаров, у которых данные отличаются


        $this->add_to_log('Cтарт функции load_stock_balance');

        $sql_all_products = "SELECT product_id, price, quantity, id_1c FROM " . DB_PREFIX . "product" .
            " WHERE id_1c IS NOT NULL";

        $query_all_products = $this->db->query($sql_all_products);

        $this->add_to_log('Запрос 1 в функции load_stock_balance выполнен');

        $part_0 = "UPDATE " . DB_PREFIX . "product SET";
        $part_1 = " price = (CASE ";
        $part_2 = " quantity = (CASE ";

        $counter_price_diff = 0;
        $counter_quantity_diff = 0;
        foreach ($query_all_products->rows as $product_opencart){
            $id_1c_opencart = $product_opencart['id_1c'];

            $product_exists_json = array_key_exists($id_1c_opencart, $stock_balance);

            if ( ! $product_exists_json){
                continue;
            } else {
                $product_from_json = $stock_balance[$id_1c_opencart];
            }

            if ($product_from_json['price'] != $product_opencart['price']){
                $counter_price_diff ++;
                $part_1 .=
                    " WHEN id_1c = '" . $this->db->escape($id_1c_opencart) .
                    "' THEN " . (double)$product_from_json['price'];
            }

            if ($product_from_json['balance'] != $product_opencart['quantity']) {
                $counter_quantity_diff++;
                $part_2 .=
                    " WHEN id_1c = '" . $this->db->escape($id_1c_opencart) .
                    "' THEN " . (double)$product_from_json['balance'];
            }
        }

        if ($counter_price_diff > 0){
            $part_1 .= " ELSE price END)";
        } else {
            unset($part_1);
        }

        if ($counter_quantity_diff > 0){
            $part_2 .= " ELSE quantity END)";
        } else {
            unset($part_2);
        }


        if (isset($part_1) && isset ($part_2)) {
            $sql_update_balance = $part_0 . $part_1 . ' ,' . $part_2;

        } elseif (isset($part_1) && ! isset($part_2)){
            $sql_update_balance = $part_0 . $part_1;

        } elseif ( ! isset($part_1) && isset($part_2)){
            $sql_update_balance = $part_0 . $part_2;
        }

        if (isset($sql_update_balance)){
            $this->add_to_log($sql_update_balance);
            $this->db->query($sql_update_balance);
            $this->add_to_log('Выполнен запрос на обновление баланса в load_stock_balance');
        }


    }

    private function generate_seo_url($name, $id='')
    {

        if ($id == ''){
            $seo_url = $this->translit($name);
        } else {
            $seo_url = $this->translit($name) . '-' . (string)$id;
        }

        return $seo_url;
    }

    private function translit($value)
    {
        $converter = array(
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'shh', 'ь' => '', 'ы' => 'y', 'ъ' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        );

        $value = mb_strtolower($value);
        $value = strtr($value, $converter);
        $value = mb_ereg_replace('[^-0-9a-z]', '-', $value);
        $value = mb_ereg_replace('[-]+', '-', $value);
        $value = trim($value, '-');

        return $value;
    }

// Функции логирования
    function add_to_log($message = '')
    {

        $log = __DIR__ . "/log_exchange.log";

        $message = $this->add_date_time_to_record($message);

        $message = $message . PHP_EOL;

        $handle = fopen($log, "a");
        fwrite($handle, $message);
        fclose($handle);
    }

    function add_date_time_to_record($record)
    {

        $record = date('Y-m-d H:i:s') . " " . $record;

        return $record;
    }


    public function get_exists_categories()
    {

        $this->add_to_log('Старт функции get_exists_categories');

        $query =
            $this->db->query("SELECT category_id, name FROM " . DB_PREFIX . "category_description");

        $this->add_to_log('Запрос в  функции get_exists_categories выполнен');

        $exists_categories = array();

        foreach ($query->rows as $result) {
            $exists_categories[$result['category_id']] = $result['name'];
        }

        return $exists_categories;

    }

}
