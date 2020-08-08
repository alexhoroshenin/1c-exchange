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

    public function index(){
        $this->add_to_log("Старт общего тестирования");

    }

    public function load_data()
    {

        $this->add_to_log('Старт функции load_data ' . $_SERVER["CONTENT_TYPE"]);  // отладка

        if ($_SERVER["CONTENT_TYPE"] == "application/json" && $_SERVER['REQUEST_METHOD'] == "POST") {
            $this->load_json();

        } elseif (strstr($_SERVER["CONTENT_TYPE"], "image") && $_SERVER['REQUEST_METHOD'] == "POST") {
            $this->load_image();
        }

        $this->response_body['type'] = $_SERVER["CONTENT_TYPE"]; // отладка
        $this->send_response_message();

    }

    private function load_json()
    {

        $this->add_to_log('Старт функции load_json'); //отладка

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

    private function send_response_message()
    {
        $response_json = json_encode($this->response_body);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput($response_json);
    }

    private function load_categories($categories_from_json)
    {

        $this->add_to_log('Старт функции load_categories');  //отладка

        $exists_categories = $this->get_exists_categories();

        foreach ($categories_from_json as $category_name) {

            $it_exist = in_array($category_name, $exists_categories);

            if (!$it_exist) {
                $this->create_category($category_name);
            }
        }
    }

    private function load_products($products)
    {

        $this->add_to_log('Старт функции load_products');   //отладка

        $exists_products_id_1c = $this->get_products_id_1c();
        $exists_categories = $this->get_exists_categories();

        foreach ($products as $product_from_json) {
            $id_1c_from_json = $product_from_json['product_id_1c'];

            $id_of_exists_product = array_search($id_1c_from_json, $exists_products_id_1c);

            $this->add_to_log('id_1c_from_json ' . $id_1c_from_json);   //отладка
            $this->add_to_log('id_of_exists_product ' . $id_of_exists_product);   //отладка

            if ($id_of_exists_product == false) {
                $this->add_to_log('Создаем продукт');   //отладка
                $this->create_product($product_from_json, $exists_categories);

            } else {
                $this->add_to_log('Обновляем продукт');   //отладка
                $this->update_product($product_from_json, $id_of_exists_product);

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

        //Получаем список всех товаров из opencart
        //Сравниваем остатки и цены с json
        //Обновляем информацию товаров, у которых данные отличаются


        $this->add_to_log('Cтарт функции load_stock_balance');

        $sql_all_products = "SELECT product_id, price, quantity, id_1c FROM " . DB_PREFIX . "product" .
            " WHERE id_1c IS NOT NULL";

        $query_all_products = $this->sql_query($sql_all_products);

        $part_0 = "UPDATE " . DB_PREFIX . "product SET";
        $part_1 = " price = (CASE ";
        $part_2 = " quantity = (CASE ";

        $counter_price_diff = 0;
        $counter_quantity_diff = 0;
        foreach ($query_all_products->rows as $product_opencart){
            $id_1c = $product_opencart['id_1c'];

            $product_exists_json = array_key_exists($id_1c, $stock_balance);

            if ( ! $product_exists_json){
                continue;
            } else {
                $product_from_json = $stock_balance[$id_1c];
            }

            if ($product_from_json['price'] != $product_opencart['price']){
                $counter_price_diff ++;
                $part_1 .=
                    " WHEN id_1c = '" . $this->db->escape($id_1c) .
                    "' THEN " . (double)$product_from_json['price'];
            }

            if ($product_from_json['balance'] != $product_opencart['quantity']) {
                $counter_quantity_diff++;
                $part_2 .=
                    " WHEN id_1c = '" . $this->db->escape($id_1c) .
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


        if (isset($part_1) && isset($part_2)) {
            $sql_update_balance = $part_0 . $part_1 . ' ,' . $part_2;

        } elseif (isset($part_1) && ! isset($part_2)){
            $sql_update_balance = $part_0 . $part_1;

        } elseif ( ! isset($part_1) && isset($part_2)){
            $sql_update_balance = $part_0 . $part_2;
        }

        if (isset($sql_update_balance)){
            $this->sql_query($sql_update_balance);
        }

        $this->add_to_log('Конец функции load_stock_balance');


    }

    private function load_image()
    {


        $image_name = $_GET['filename'];  //пример g000112345_ht0947312.jpg
        $input_image = file_get_contents('php://input');

        if (!$image_name || !$input_image) {
            return false;
        }

        $this->add_to_log("Загружаем картинку " . $image_name);

        $folder_name = 'import_1c/';
        $folder_path = $this->create_or_get_directory(DIR_IMAGE . $folder_name); // domain.ru/image/import_1c/
        $filename = $folder_path . $image_name; // domain.ru/image/import_1c/000112345_ht0947312.jpg

        $this->add_to_log("filename " . $filename);

        $it_exists = file_exists($filename);

        if ($it_exists) {
            $this->set_image_to_product($filename, $image_name, $folder_name);
            $this->add_to_log("Картинка уже существует");

        } else {
            $this->add_to_log("Картинка НЕ существует");
            $saved_file = file_put_contents($folder_path . $image_name, $input_image);

            if ($saved_file) {
                $this->add_to_log($image_name . ' картинка получена и сохранена');

                $this->set_image_to_product($filename, $image_name, $folder_name);
            }
        }
    }

    private function set_image_to_product($filename, $image_name, $folder_name){

        $this->add_to_log("Старт функции set_image_to_product");

        //$filename - absolute path, пример domain.ru/image/import_1c/000112345_ht0947312.jpg
        //$image_name, пример g000112345_ht0947312.jpg
        //$folder_name папка для картинок из 1С: import_1c/

        $image_name_first_part = $this->get_filename_first_part($image_name, '_');
        //$image_name_first_part - это id_1c без дефисов

        $sql_get_product_images =
            "SELECT p.product_id, id_1c, p.image AS main_image, pi.image AS additional_image" .
            " FROM " . DB_PREFIX . "product AS p" .
            " LEFT JOIN " . DB_PREFIX . "product_image AS pi ON p.product_id = pi.product_id" .
            " WHERE REPLACE(id_1c , '-' , '') = '" . $this->db->escape($image_name_first_part) . "'";

        $query = $this->sql_query($sql_get_product_images);

        if($query->num_rows == 0){

            $this->add_to_log('НЕ найден товар с идентификатором ' . $image_name_first_part);
            return false;
        }

        $product_id = (int)$query->row['product_id'];

        $isset_main_image = false;
        $main_image = '';
        $it_additional_image = false;

        foreach($query->rows as $row){

            if($row['main_image'] != ''){
                $isset_main_image = true;
                $main_image = $row['main_image'];
            }

            if($row['additional_image'] == ($folder_name . $image_name)){
                $it_additional_image = true;
            }
        }

        $this->add_to_log('isset_main_image ' . $isset_main_image);
        $this->add_to_log('main_image ' . $main_image);
        $this->add_to_log('$it_additional_image ' . $it_additional_image);

        if (!$isset_main_image){
            //У товара нет основной картинки. Ставим загруженную.

            $this->add_to_log("Загружаем картинку как основную");

            $sql_main_image =
                "UPDATE " . DB_PREFIX . "product SET image = '" .
                $this->db->escape($folder_name . $image_name) .
                "' WHERE product_id =" . $product_id;

            $this->sql_query($sql_main_image);
        } elseif ($main_image != ($folder_name . $image_name) && !$it_additional_image) {
            //Загруженная картинка не является основной и не находится в дополнительных
            //Добавляем эту картинку в дополнительные

            $this->add_to_log("Загружаем картинку как дополнительную");

            $sql_image =
                "INSERT INTO " . DB_PREFIX . "product_image SET product_id = '" . $product_id .
                "', image = '" . $this->db->escape($folder_name . $image_name) . "'";

            $this->sql_query($sql_image);

        }
    }

    private function get_exists_categories()
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

    private function create_category($name)
    {

        $this->add_to_log('Старт функции create_category'); //отладка

        //СОздаем категорию
        $sql_category = "INSERT INTO " . DB_PREFIX . "category SET " .
            "parent_id = 0" .
            ", `top` = 1" .
            ", `column` = 1" .
            ", sort_order = 0" .
            ", status = 1" .
            ", date_modified = NOW(), date_added = NOW()";

        $this->sql_query($sql_category);

        $category_id = $this->db->getLastId();

        //Создаем описание категории
        $sql_category_description = "INSERT INTO " . DB_PREFIX . "category_description SET " .
            "category_id = " . (int)$category_id .
            ", language_id = 1" .
            ", name = '" . $this->db->escape($name) .
            "', meta_title =  '" . $this->db->escape($name) . "'";

        $this->sql_query($sql_category_description);


        // Устанавливаем иерарзию для категории
        $level = 0;

        $sql_category_path = "INSERT INTO `" . DB_PREFIX . "category_path` SET `category_id` = '" . (int)$category_id .
            "', `path_id` = '" . (int)$category_id . "', `level` = '" . (int)$level . "'";

        $this->sql_query($sql_category_path);

        //SEO url
        $seo_url = $this->generate_seo_url($name);

        $sql_seo_url = "INSERT INTO " . DB_PREFIX . "seo_url SET store_id = 0" .
            ", language_id = 1" . ", query = 'category_id=" . (int)$category_id .
            "', keyword = '" . $this->db->escape($seo_url) . "'";

        $this->sql_query($sql_seo_url);

        //Устанавливаем принадлежность категории к основному магазину
        $sql_category_to_shop = "INSERT INTO " . DB_PREFIX . "category_to_store SET store_id = 0" .
            ", category_id=" . (int)$category_id;

        $this->sql_query($sql_category_to_shop);

        $this->add_to_log('Функция create_category завершила выполнение'); //отладка

    }

    private function get_products_id_1c()
    {
        $query =
            $this->sql_query("SELECT DISTINCT product_id, id_1c FROM "
                . DB_PREFIX . "product WHERE id_1c IS NOT NULL");

        $products_id_1c = array();

        foreach ($query->rows as $result) {
            $products_id_1c[$result['product_id']] = $result['id_1c'];
        }

        return $products_id_1c;
    }

    private function update_product($product_from_json, $exists_categories)
    {

        $this->add_to_log('Старт функции update_product'); //отладка

        $id_1c = $product_from_json['product_id_1c'];

        //Получаем описание товара
        $sql_product =
            "SELECT p.product_id, p.model, p.stock_status_id, p.minimum, d.name, d.meta_title" .
            " FROM oc_product AS p" .
            " LEFT JOIN oc_product_description AS d ON p.product_id = d.product_id" .
            " WHERE p.id_1c = '" . $this->db->escape($id_1c) . "'";

        $query = $this->sql_query($sql_product);

        $id_opencart = (int)$query->row['product_id'];

        $is_equal_description = $this->compare_product_description($product_from_json, $query->row);

        if (!$is_equal_description) {
            $this->change_product_description($id_opencart, $product_from_json);
        }

        //Сравнение категории в opencart и 1с.
        //В опенкарт может быть больше одной категории

        $sql_categories =
            "SELECT cd.category_id, cd.name " .
            " FROM " . DB_PREFIX . "category_description AS cd" .
            " INNER JOIN " . DB_PREFIX . "product_to_category AS pc" .
            " ON cd.category_id = pc.category_id" .
            " WHERE pc.product_id = " . $id_opencart;

        $query = $this->sql_query($sql_categories);

        $isset_category = $this->this_category_isset_in_product($product_from_json, $query->rows);

        if (!$isset_category) {
            $this->change_product_category($id_opencart, $product_from_json, $query->rows);
        }

    }

    private function create_product($product_from_json, $exists_categories)
    {

        $this->add_to_log('Старт функции create_product');

        //Создаем продукт
        $sql_product = "INSERT INTO " . DB_PREFIX . "product SET " .
            " model = '" . $product_from_json['model'] . "'" .
            ", stock_status_id =" . (int)($product_from_json['stock_status_id']) .
            ", id_1c = '" . $this->db->escape($product_from_json['product_id_1c']) .
            "', quantity = 0" .
            ", minimum = 1" .
            ", price = 1" .
            ", status = 1" .
            ", date_added = NOW(), date_modified = NOW()";

        $this->sql_query($sql_product);

        $product_id = $this->db->getLastId();

        $sql_product_description = "INSERT INTO " . DB_PREFIX . "product_description SET" .
            " product_id = '" . (int)$product_id .
            "', language_id = 1" .
            ", name = '" . $this->db->escape($product_from_json['name']) .
            "', description = '" . $this->db->escape($product_from_json['description']) .
            "', tag = '" .
            "', meta_title = '" . $this->db->escape($product_from_json['name']) .
            "', meta_description = '" . $this->db->escape($product_from_json['meta_description']) .
            "', meta_keyword ='' ";

        $this->sql_query($sql_product_description);

        $seo_url = $this->generate_seo_url($product_from_json['name'], $product_id);

        $sql_seo_url = "INSERT INTO " . DB_PREFIX . "seo_url SET store_id = 0" .
            ", language_id = 1" . ", query = 'product_id=" . (int)$product_id .
            "', keyword = '" . $this->db->escape($seo_url) . "'";

        $this->sql_query($sql_seo_url);


        $sql_product_to_store = "INSERT INTO " . DB_PREFIX . "product_to_store SET store_id = 0"
            .", product_id=" . (int)$product_id;

        $this->sql_query($sql_product_to_store);


        $category_id = $this->get_product_category_id($product_from_json, $exists_categories);

        if ($category_id){
            $sql_product_to_category = "INSERT INTO " . DB_PREFIX . "product_to_category SET product_id = " .
                (int)$product_id . ", category_id = " . (int)$category_id;

            $this->sql_query($sql_product_to_category);
        }

        $this->cache->delete('product');

        $this->add_to_log('Функция create_product завершила работу');

    }

    private function get_product_category_id($product_from_json, $exists_categories)
    {

        if (isset($product_from_json['group_id_1c'])) {
            $group_id_1c = $product_from_json['group_id_1c'];
            $category = array_search($group_id_1c, $exists_categories);
            return $category;
        } else {
            return false;
        }

    }

    private function sql_query($sql)
    {
        try {
            $query = $this->db->query($sql);
            return $query;

        } catch (Exception $e){
            $this->add_to_log("Ошибка при выполнении запроса SQL");
            $this->add_to_log($sql);
            return false;
        }
    }

    private function compare_product_description($product_from_json, $product_from_opencart)
    {
        $this->add_to_log('Старт функции compare_product_description');

        // поля в $product_from_opencart
        // product_id, model, stock_status_id, minimum, name, meta_title

        if ($product_from_opencart['model'] == $product_from_json['model'] &&
            $product_from_opencart['stock_status_id'] == $product_from_json['stock_status_id'] &&
            $product_from_opencart['minimum'] == $product_from_json['minimum'] &&
            $product_from_opencart['name'] == $product_from_json['name']) {

            return true;
        }

        return false;
    }

    private function change_product_description($id_opencart, $product_from_json)
    {

        $this->add_to_log('Старт функции change_product_description'); //отладка

        $sql_product_change_model = "UPDATE " . DB_PREFIX . "product SET model = '" .
            $this->db->escape($product_from_json['model']) .
            "', stock_status_id = '" . (integer)$product_from_json['stock_status_id'] .
            "', minimum = " . (integer)$product_from_json['minimum'] .
            " WHERE product_id = '" . $this->db->escape($id_opencart) . "'";

        $this->sql_query($sql_product_change_model);

        $sql_product_change_description = "UPDATE " . DB_PREFIX . "product_description SET " .
            "name = '" . $this->db->escape($product_from_json['name']) .
            "', description = '" . $this->db->escape($product_from_json['description']) .
            "', meta_description = '" . $this->db->escape($product_from_json['meta_description']) .
            "', meta_title = '" . $this->db->escape($product_from_json['name']) .
            "' WHERE product_id = '" . $id_opencart . "'";

        $this->sql_query($sql_product_change_description);

        $this->add_to_log('Конец функции change_product_description'); //отладка

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

    private function change_product_category($id_opencart, $product_from_json, $categories_from_query)
    {

        $this->add_to_log('Старт функции change_product_category');

        $category_from_json = $product_from_json['category'];

        foreach ($categories_from_query as $category) {
            if ($category['name'] == $category_from_json) {

                $category_id = $category['category_id'];
                //нужная категория найдена в списке категорий, добавим ее в продукт

                $sql_set_category = "INSERT INTO " . DB_PREFIX . "product_to_category SET " .
                    "product_id = " . (integer)$id_opencart .
                    ", category_id = " . (integer)$category_id;

                $this->sql_query($sql_set_category);

                break;

            }
        }
    }

    private function create_or_get_directory($path){

        if (!file_exists($path)) {
            mkdir($path, 0777);
            $this->add_to_log("Создана дирректория " . $path);
        }

        return $path;
    }

    private function get_filename_first_part($file_name, $delimeter){

        $pieces = explode($delimeter, $file_name);

        return $pieces[0];
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

    private function add_to_log($message = '')
    {

        $log = __DIR__ . "/log_exchange.log";

        $message = date('Y-m-d H:i:s') . " " .  $message . PHP_EOL;

        $handle = fopen($log, "a");
        fwrite($handle, $message);
        fclose($handle);
    }

}
