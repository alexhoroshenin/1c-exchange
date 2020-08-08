<?php

// Функции логирования
function add_to_log($message=''){

    $log = __DIR__ . "/log_exchange.log";

    $message = add_date_time_to_record($message);

    $message = $message . PHP_EOL;

    $handle = fopen($log, "a");
    fwrite($handle, $message);
    fclose($handle);
}

function add_date_time_to_record($record){

    $record = date('Y-m-d H:i:s') . " " . $record;

    return $record;
}


//echo __DIR__ . "/log_exchange.log";
