<?php

return [
    // ВАЖНО: задай свою строку 32+ символа, случайную.
    // Не коммить в публичный репо.
    'app_key' => 'CX3DRSz5d9u5Bbn3HOJTEQZAWnDfz756',

    'env' => 'prod', // prod|dev

    // FASTPANEL
   'fastpanel' => [
        'timeout' => 30,
        'default_ip' => '95.129.234.52', // тот IP, который выбираешь в мастере
        'add_www_alias' => true,
		 'ssl_email' => 'support@pro-managed.com',
		 'temp_proxy_base' => 'http://95.129.234.20:7777',
    ],
	
	'namecheap' => [
      'sandbox' => true,
      'endpoint_sandbox' => 'https://api.sandbox.namecheap.com/xml.response',
      'endpoint'         => 'https://api.namecheap.com/xml.response',
      'max_price_usd'    => 7.0,
      'timeout'          => 30,
  ],

];



