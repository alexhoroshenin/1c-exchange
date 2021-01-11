<?php
/*
В таблицах товаров и категорий важно создать ограничение на уникальность поля product_id_1c,
category_id_1c, чтобы не дублировать товары

ALTER TABLE oc_product ADD COLUMN product_id_1c VARCHAR(100) DEFAULT NULL;
необязательно -- ALTER TABLE oc_product ADD UNIQUE (product_id_1c);

ALTER TABLE oc_category ADD COLUMN category_id_1c VARCHAR(100) DEFAULT NULL;

Возможно будет ошибка в пункте 1: Invalid default value for 'date_available'
Для этого нужно установить дефолтные значения для этого поля '2000-01-01'

ALTER TABLE oc_product CHANGE COLUMN date_available date_available DATETIME NOT NULL DEFAULT '2001-01-01';
*/


class ControllerApiExchange1c extends Controller
{
    public $response_body = array();

    public $message = array();

    public function index()
    {

        $input_json = file_get_contents('php://input');
        $decode_input = json_decode($input_json, true);

        //print_r($decode_input['categories']);

        $this->add_to_response_message('first row');

        $this->send_response_message();

    }

    public function load(){

        if ($_SERVER['REQUEST_METHOD'] == "POST" && $_SERVER["CONTENT_TYPE"] == "application/json") {

            $this->prepare_db();

            $this->add_to_response_message("Загрузка json");
            $this->load_json();

            //отправка отчета
            $this->send_response_message();

        } elseif ($_SERVER['REQUEST_METHOD'] == "POST" && strstr($_SERVER["CONTENT_TYPE"], "image")) {
            $this->load_image();
            echo 'success';

        } elseif ($_SERVER['REQUEST_METHOD'] == "GET") {
            echo 'success';
        }
    }

    private function prepare_db()
    {

        //Проверяем наличие ограничения UNIQUE для таблицы товаров

        $sql_check = "select * from INFORMATION_SCHEMA.TABLE_CONSTRAINTS where " .
            " CONSTRAINT_TYPE='UNIQUE' and TABLE_SCHEMA = '" . DB_DATABASE .
            "' and CONSTRAINT_NAME = 'product_id_1c' and table_name = '" . DB_PREFIX .  "product' ";

        $query = $this->sql_query($sql_check);

        if ($query->num_rows == 0){
            $sql_add_constraint_product = "ALTER TABLE " . DB_PREFIX . "product ADD UNIQUE (product_id_1c)";
            $this->sql_query($sql_add_constraint_product);
        }
        //Проверяем наличие ограничения UNIQUE для таблицы категорий
        $sql_check = "select * from INFORMATION_SCHEMA.TABLE_CONSTRAINTS where " .
            " CONSTRAINT_TYPE='UNIQUE' and TABLE_SCHEMA = '" . DB_DATABASE .
            "' and CONSTRAINT_NAME = 'category_id_1c' and table_name = '" . DB_PREFIX .  "category' ";

        $query = $this->sql_query($sql_check);

        if ($query->num_rows == 0) {
            $sql_add_constraint_category = "ALTER TABLE " . DB_PREFIX . "category ADD UNIQUE (category_id_1c)";
            $this->sql_query($sql_add_constraint_category);
        }

    }

    private function load_json()
    {

        $input_json = file_get_contents('php://input');
        $decode_input = json_decode($input_json, true);

        if (isset($decode_input['categories'])) {
            $this->add_to_response_message("Загрузка категорий");
            $this->load_categories($decode_input['categories']);
        }

        if (isset($decode_input['products'])) {
            $this->add_to_response_message("Загрузка товаров");
            $this->load_products($decode_input['products']);
        }

        if (isset($decode_input['offers'])) {
            $this->add_to_response_message("Загрузка остатков");
            $this->load_stock_balance($decode_input['offers']);
        }
    }

    private function send_response_message()
    {
        $response = array('message' => $this->message);
        $response_json = json_encode($response);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput($response_json);
    }

    private function load_categories($categories_from_json)
    {

        foreach ($categories_from_json as $category_from_json) {

            $category_id_1c = $category_from_json['category_id_1c'];

            $category_from_opencart = $this->category_in_list($category_id_1c);

            if (!$category_from_opencart) {
                $this->add_to_response_message("Создаем новую категорию " . $category_from_json['name']);
                $this->create_category($category_from_json);
            } else {
                $this->add_to_response_message("Обновляем категорию " . $category_from_json['name']);
                $this->update_category($category_from_opencart, $category_from_json);
            }
        }
    }

    private function load_products($products)
    {

        $exists_products_id_1c = $this->get_products_id_1c();

        foreach ($products as $product_from_json) {
            $id_1c_from_json = $product_from_json['product_id_1c'];

            $id_of_exists_product = array_search($id_1c_from_json, $exists_products_id_1c);

            if ($id_of_exists_product == false) {
                $this->add_to_response_message('Создаем продукт ' . $product_from_json['name']);
                $this->create_product($product_from_json);

            } else {
                $this->add_to_response_message('Обновляем продукт ' . $product_from_json['name']);
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

        $sql_all_products = "SELECT product_id, price, quantity, product_id_1c FROM " . DB_PREFIX . "product" .
            " WHERE product_id_1c IS NOT NULL";

        $query_all_products = $this->sql_query($sql_all_products);

        $part_0 = "UPDATE " . DB_PREFIX . "product SET";
        $part_1 = " price = (CASE ";
        $part_2 = " quantity = (CASE ";

        $counter_price_diff = 0;
        $counter_quantity_diff = 0;
        foreach ($query_all_products->rows as $product_opencart) {
            $id_1c = $product_opencart['product_id_1c'];

            $product_from_json = $this->array_return_row_by_value($id_1c, $stock_balance);

            if (!$product_from_json) {
                continue;
            }

            if ($product_from_json['price'] != $product_opencart['price']) {
                $counter_price_diff++;
                $part_1 .=
                    " WHEN product_id_1c = '" . $this->db->escape($id_1c) .
                    "' THEN " . (double)$product_from_json['price'];
            }

            if ($product_from_json['quantity'] != $product_opencart['quantity']) {
                $counter_quantity_diff++;
                $part_2 .=
                    " WHEN product_id_1c = '" . $this->db->escape($id_1c) .
                    "' THEN " . (double)$product_from_json['quantity'];
            }
        }

        if ($counter_price_diff > 0) {
            $part_1 .= " ELSE price END)";
        } else {
            unset($part_1);
        }

        if ($counter_quantity_diff > 0) {
            $part_2 .= " ELSE quantity END)";
        } else {
            unset($part_2);
        }


        if (isset($part_1) && isset($part_2)) {
            $sql_update_balance = $part_0 . $part_1 . ' ,' . $part_2;

        } elseif (isset($part_1) && !isset($part_2)) {
            $sql_update_balance = $part_0 . $part_1;

        } elseif (!isset($part_1) && isset($part_2)) {
            $sql_update_balance = $part_0 . $part_2;
        }

        if (isset($sql_update_balance)) {
            $this->sql_query($sql_update_balance);
        }

        $this->add_to_log('Конец функции load_stock_balance');


    }

    private function array_return_row_by_value($searching_value, $array){

        foreach ($array as $row){
            foreach ($row as $key => $value){
                if ($value == $searching_value){
                    return $row;
                }
            }
        }

        return false;
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

    private function set_image_to_product($filename, $image_name, $folder_name)
    {

        $this->add_to_log("Старт функции set_image_to_product");

        //$filename - absolute path, пример domain.ru/image/import_1c/000112345_ht0947312.jpg
        //$image_name, пример g000112345_ht0947312.jpg
        //$folder_name папка для картинок из 1С: import_1c/

        $image_name_first_part = $this->get_filename_first_part($image_name, '_');
        //$image_name_first_part - это id_1c без дефисов

        $sql_get_product_images =
            "SELECT p.product_id, p.product_id_1c, p.image AS main_image, pi.image AS additional_image" .
            " FROM " . DB_PREFIX . "product AS p" .
            " LEFT JOIN " . DB_PREFIX . "product_image AS pi ON p.product_id = pi.product_id" .
            " WHERE p.product_id_1c = '" . $this->db->escape($image_name_first_part) . "'";

        $query = $this->sql_query($sql_get_product_images);

        if ($query->num_rows == 0) {

            $this->add_to_log('НЕ найден товар с идентификатором ' . $image_name_first_part);
            return false;
        }

        $product_id = (int)$query->row['product_id'];

        $isset_main_image = false;
        $main_image = '';
        $it_additional_image = false;

        foreach ($query->rows as $row) {

            if ($row['main_image'] != '') {
                $isset_main_image = true;
                $main_image = $row['main_image'];
            }

            if ($row['additional_image'] == ($folder_name . $image_name)) {
                $it_additional_image = true;
            }
        }

        $this->add_to_log('isset_main_image ' . $isset_main_image);
        $this->add_to_log('main_image ' . $main_image);
        $this->add_to_log('$it_additional_image ' . $it_additional_image);

        if (!$isset_main_image) {
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

        $query =
            $this->sql_query("SELECT category_id, category_id_1c FROM " . DB_PREFIX .
                "category WHERE category_id_1c IS NOT NULL");

        return $query->rows;

    }

    private function create_category($category)
    {
        $category_parent_id_1c = $category['parent_id_1c'];
        $category_name = $category['name'];
        $category_id_1c = $category['category_id_1c'];

        if ($category_parent_id_1c) {
            $parent_opencart_id = $this->get_parent_opencart_id($category_parent_id_1c);
        } else {
            $parent_opencart_id = 0;
        }

        $sql_category = "INSERT INTO " . DB_PREFIX . "category SET " .
            "`parent_id` = '" . $this->db->escape($parent_opencart_id) .
            "', `top` = 1" .
            ", `column` = 1" .
            ", `status` = 1" .
            ", `category_id_1c` = '" . $this->db->escape($category_id_1c) .
            "', `date_modified` = NOW(), `date_added` = NOW()";

        $this->sql_query($sql_category);

        $category_id = $this->db->getLastId();

        //Создаем описание категории
        $sql_category_description = "INSERT INTO " . DB_PREFIX . "category_description SET " .
            "category_id = " . (int)$category_id .
            ", language_id = 1" .
            ", name = '" . $this->db->escape($category_name) .
            "', meta_title =  '" . $this->db->escape($category_name) . "'";

        $this->sql_query($sql_category_description);


        // Устанавливаем иерархию для категории

        if (!$parent_opencart_id) {

            $level = 0;

            $sql_category_path =
                "INSERT INTO `" . DB_PREFIX . "category_path` SET `category_id` = '" . (int)$category_id .
                "', `path_id` = '" . (int)$category_id . "', `level` = '" . (int)$level . "'";

            $this->sql_query($sql_category_path);

        } else {
            // MySQL Hierarchical Data Closure Table Pattern
            $level = 0;

            $query = $this->sql_query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" .
                (int)$parent_opencart_id . "' ORDER BY `level` ASC");

            foreach ($query->rows as $result) {
                $this->sql_query("INSERT INTO `" . DB_PREFIX . "category_path` SET `category_id` = '" .
                    (int)$category_id .
                    "', `path_id` = '" . (int)$result['path_id'] . "', `level` = '" . (int)$level . "'");

                $level++;
            }

            $this->sql_query("INSERT INTO `" . DB_PREFIX . "category_path` SET `category_id` = '" . (int)$category_id .
                "', `path_id` = '" . (int)$category_id . "', `level` = '" . (int)$level . "'");

            ////////
        }


        //SEO url
        $seo_url = $this->generate_seo_url($category_name);

        $sql_seo_url = "INSERT INTO " . DB_PREFIX . "seo_url SET store_id = 0" .
            ", language_id = 1" . ", query = 'category_id=" . (int)$category_id .
            "', keyword = '" . $this->db->escape($seo_url) . "'";

        $this->sql_query($sql_seo_url);

        //Устанавливаем принадлежность категории к основному магазину
        $sql_category_to_shop = "INSERT INTO " . DB_PREFIX . "category_to_store SET store_id = 0" .
            ", category_id=" . (int)$category_id;

        $this->sql_query($sql_category_to_shop);

    }

    private function get_parent_opencart_id($category_parent_id_1c)
    {

        if ($category_parent_id_1c == '') {
            return 0;
        }

        $sql = "SELECT category_id FROM " . DB_PREFIX . "category WHERE category_id_1c = '"
            . $this->db->escape($category_parent_id_1c) . "'";

        $query = $this->sql_query($sql);

        if ($query->num_rows > 0) {
            return $query->row['category_id'];
        }

        return 0;

    }

    private function update_category($category_id, $category_from_json)
    {

        $category_parent_id_1c = $category_from_json['parent_id_1c'];
        $category_name_1c = $category_from_json['name'];
        $category_id_1c = $category_from_json['category_id_1c'];

        //обновляем название

        $sql_update_name = "UPDATE " . DB_PREFIX . "category_description SET " .
            "name = '" . $this->db->escape($category_name_1c) . "'" .
            " WHERE category_id = '" . $this->db->escape($category_id) .
            "' AND name <> '" . $this->db->escape($category_name_1c) . "'";

        $this->sql_query($sql_update_name);

        //обновляем родителя

        $parent_opencart_id = $this->get_parent_opencart_id($category_parent_id_1c);

        if ($parent_opencart_id) {

            $sql_update_category_parent = "UPDATE " . DB_PREFIX . "category SET " .
                "`parent_id` = " . $this->db->escape($parent_opencart_id) .
                ", `date_modified` = NOW() WHERE `category_id_1c` = '" . $this->db->escape($category_id_1c) . "'";

            $this->sql_query($sql_update_category_parent);
        }

        //обновляем вложенность

        // MySQL Hierarchical Data Closure Table Pattern
        $query =
            $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE path_id = '" . (int)$category_id .
                "' ORDER BY level ASC");

        if ($query->rows) {
            foreach ($query->rows as $category_path) {
                // Delete the path below the current one
                $this->db->query("DELETE FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" .
                    (int)$category_path['category_id'] . "' AND level < '" . (int)$category_path['level'] . "'");

                $path = array();

                // Get the nodes new parents
                $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" .
                    (int)$parent_opencart_id . "' ORDER BY level ASC");

                foreach ($query->rows as $result) {
                    $path[] = $result['path_id'];
                }

                // Get whats left of the nodes current path
                $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" .
                    (int)$category_path['category_id'] . "' ORDER BY level ASC");

                foreach ($query->rows as $result) {
                    $path[] = $result['path_id'];
                }

                // Combine the paths with a new level
                $level = 0;

                foreach ($path as $path_id) {
                    $this->db->query("REPLACE INTO `" . DB_PREFIX . "category_path` SET category_id = '" .
                        (int)$category_path['category_id'] . "', `path_id` = '" . (int)$path_id . "', level = '" .
                        (int)$level . "'");

                    $level++;
                }
            }
        } else {
            // Delete the path below the current one
            $this->db->query("DELETE FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$category_id .
                "'");

            // Fix for records with no paths
            $level = 0;

            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" .
                (int)$parent_opencart_id . "' ORDER BY level ASC");

            foreach ($query->rows as $result) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET category_id = '" .
                    (int)$category_id . "', `path_id` = '" . (int)$result['path_id'] . "', level = '" . (int)$level .
                    "'");

                $level++;
            }

            $this->db->query("REPLACE INTO `" . DB_PREFIX . "category_path` SET category_id = '" . (int)$category_id .
                "', `path_id` = '" . (int)$category_id . "', level = '" . (int)$level . "'");
        }

    }

    private function get_products_id_1c()
    {
        $query =
            $this->sql_query("SELECT DISTINCT product_id, product_id_1c FROM "
                . DB_PREFIX . "product WHERE product_id_1c IS NOT NULL");

        $products_id_1c = array();

        foreach ($query->rows as $result) {
            $products_id_1c[$result['product_id']] = $result['product_id_1c'];
        }

        return $products_id_1c;
    }

    private function update_product($product_from_json, $id_of_exists_product)
    {

        $products_id_1c = $product_from_json['product_id_1c'];

        $category_id = $this->category_in_list($product_from_json['group_id_1c']);

        //Получаем описание товара
        $sql_product =
            "SELECT p.product_id, p.model, p.stock_status_id, p.minimum, d.name, d.meta_title" .
            " FROM oc_product AS p" .
            " LEFT JOIN oc_product_description AS d ON p.product_id = d.product_id" .
            " WHERE p.product_id_1c = '" . $this->db->escape($products_id_1c) . "'";

        $query = $this->sql_query($sql_product);

        $id_opencart = (int)$query->row['product_id'];

        $is_equal_description = $this->compare_product_description($product_from_json, $query->row);

        if (!$is_equal_description) {
            $this->change_product_description($id_opencart, $product_from_json);
        }

        //Сравнение категории в opencart и 1с.
        //В опенкарт может быть больше одной категории

        $sql_categories =

            "SELECT category_id " .
            " FROM " . DB_PREFIX . "product_to_category " .
            " WHERE product_id = '" . $this->db->escape($id_opencart) . "' AND " .
            "category_id = '" . $this->db->escape($category_id) . "'";


        $query = $this->sql_query($sql_categories);

        if ($query->num_rows == 0) {
            // delete old categories
            $sql_delete_old_categories =
                "DELETE FROM " . DB_PREFIX . "product_to_category WHERE " .
                " product_id = '" . $this->db->escape($id_opencart) . "'";

            $this->sql_query($sql_delete_old_categories);

            //set new category
            $sql_set_new_category =
                "INSERT INTO " . DB_PREFIX . "product_to_category SET " .
                " product_id = '" . $this->db->escape($id_opencart) .
                "' , category_id = '" . $this->db->escape($category_id) . "'";

            $this->sql_query($sql_set_new_category);
        }

    }

    private function create_product($product_from_json)
    {

        //Создаем продукт
        $sql_product = "INSERT INTO " . DB_PREFIX . "product SET " .
            " model = '" . $product_from_json['model'] . "'" .
            ", stock_status_id = 7" .
            ", product_id_1c = '" . $this->db->escape($product_from_json['product_id_1c']) .
            "', quantity = 0" .
            ", minimum = 1" .
            ", price = 0" .
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
            "', meta_description = '" .
            "', meta_keyword ='' ";

        $this->sql_query($sql_product_description);

        $seo_url = $this->generate_seo_url($product_from_json['name'], $product_id);

        $sql_seo_url = "INSERT INTO " . DB_PREFIX . "seo_url SET store_id = 0" .
            ", language_id = 1" . ", query = 'product_id=" . (int)$product_id .
            "', keyword = '" . $this->db->escape($seo_url) . "'";

        $this->sql_query($sql_seo_url);


        $sql_product_to_store = "INSERT INTO " . DB_PREFIX . "product_to_store SET store_id = 0"
            . ", product_id=" . (int)$product_id;

        $this->sql_query($sql_product_to_store);

        $category_id = $this->get_product_category_id($product_from_json);

        if ($category_id) {
            $sql_product_to_category = "INSERT INTO " . DB_PREFIX . "product_to_category SET product_id = " .
                (int)$product_id . ", category_id = " . (int)$category_id;

            $this->sql_query($sql_product_to_category);
        }

        $this->cache->delete('product');

    }

    private function category_in_list($category_id_1c)
    {

        //$list содержит двумерный массив с category_id, category_id_1c

        $list = $this->get_exists_categories();

        $category_id = false;

        foreach ($list as $category_from_list) {

            if ($category_from_list['category_id_1c'] == $category_id_1c) {
                $category_id = $category_from_list['category_id'];
                return $category_id;
            }

        }
        return $category_id;
    }

    private function get_product_category_id($product_from_json)
    {

        if (isset($product_from_json['group_id_1c'])) {

            $category_id_1c = $product_from_json['group_id_1c'];

            $category_id = $this->category_in_list($category_id_1c);

            return $category_id;

        } else {
            return false;
        }

    }

    private function sql_query($sql)
    {
        try {
            $query = $this->db->query($sql);
            return $query;

        } catch (Exception $e) {
            $this->add_to_log("Ошибка при выполнении запроса SQL");
            $this->add_to_log($sql);
            $this->add_to_response_message("Ошибка при выполнении запроса SQL");
            $this->add_to_response_message($sql);
            return false;
        }
    }

    private function compare_product_description($product_from_json, $product_from_opencart)
    {

        // поля в $product_from_opencart
        // product_id, model, stock_status_id, minimum, name, meta_title

        if ($product_from_opencart['model'] == $product_from_json['model'] &&
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
            "', stock_status_id = 7" .
            ", minimum = 1" .
            " WHERE product_id = '" . $this->db->escape($id_opencart) . "'";

        $this->sql_query($sql_product_change_model);

        $sql_product_change_description = "UPDATE " . DB_PREFIX . "product_description SET " .
            "name = '" . $this->db->escape($product_from_json['name']) .
            "', description = '" . $this->db->escape($product_from_json['description']) .
            "', meta_title = '" . $this->db->escape($product_from_json['name']) .
            "' WHERE product_id = '" . $id_opencart . "'";

        $this->sql_query($sql_product_change_description);

        $this->add_to_log('Конец функции change_product_description'); //отладка

    }


    private function create_or_get_directory($path)
    {

        if (!file_exists($path)) {
            mkdir($path, 0777);
            $this->add_to_log("Создана дирректория " . $path);
        }

        return $path;
    }

    private function get_filename_first_part($file_name, $delimeter)
    {

        $pieces = explode($delimeter, $file_name);

        return $pieces[0];
    }

    private function generate_seo_url($name, $id = '')
    {

        if ($id == '') {
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

        $log = __DIR__ . "/log_exchange.txt";

        $message = date('Y-m-d H:i:s') . " " . $message . PHP_EOL;

        $handle = fopen($log, "a");
        fwrite($handle, $message);
        fclose($handle);
    }

    private function add_to_response_message($row)
    {
        $this->message[] = $row;
    }

}
