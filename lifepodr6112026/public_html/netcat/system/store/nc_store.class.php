<?php

class nc_store {

    protected static $base_url = 'https://store.netcat.ru/api/';

    public static function get($path, array $data = array()) {
        $request = new nc_store_request(self::$base_url);
        return $request->get($path, $data);
    }

    public static function download($path, array $data = array()) {
        $request = new nc_store_request(self::$base_url);
        return $request->download($path, $data);
    }

}