<?php
$root=getenv('LP_TEST_ROOT');$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$_SERVER['DOCUMENT_ROOT']=$root;putenv('DOCUMENT_ROOT='.$root);putenv('HTTP_HOST='.preg_replace('/:\d+$/','',$_SERVER['HTTP_HOST']));putenv('REQUEST_URI='.$_SERVER['REQUEST_URI']);$_SERVER['HTTPS']='on';$_SERVER['SERVER_PORT']=443;
if(is_file($root.$path)&&!preg_match('/\.(php|html)$/',$path)){return false;}
if($path==='/'){require $root.'/index.php';}elseif(is_file($root.$path)&&substr($path,-4)==='.php'){require $root.$path;}else{require $root.'/netcat/require/e404.php';}
