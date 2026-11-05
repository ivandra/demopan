<?php

/* $Id: plaintext.php 6209 2012-02-10 10:28:29Z denis $ */

/**
 *
 */
class nc_search_logger_plaintext extends nc_search_logger {

    public function log($type_string, $message) {
        echo date("Y-m-d H:i:s"), " $type_string $message\n";
        flush();
    }

}