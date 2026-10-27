<?php
// Safe local PHP development router. Apache uses the regular PHP files/.htaccess.
$root=realpath(__DIR__.'/..');
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/');
if($path==='/') { header('Location: /smart-laundry/'); exit; }
if(!str_starts_with($path,'/smart-laundry/')) { http_response_code(404); exit('Not found'); }
$relative=substr($path,strlen('/smart-laundry/'));
if($relative==='') $relative='index.php';
if(preg_match('~(^|/)(\.|config|database|includes|tests|tools)(/|$)|\\\\|\x00~',$relative)) { http_response_code(404); exit('Not found'); }
$file=realpath($root.'/'.$relative);
if(!$file || !is_file($file) || !str_starts_with(str_replace('\\','/',$file),str_replace('\\','/',$root).'/')) { http_response_code(404); exit('Not found'); }
$extension=strtolower(pathinfo($file,PATHINFO_EXTENSION));
if($extension==='php' && basename($file)!=='setup.php') {
    $_SERVER['SCRIPT_NAME']='/smart-laundry/'.$relative;
    $_SERVER['PHP_SELF']=$_SERVER['SCRIPT_NAME'];
    $_SERVER['SCRIPT_FILENAME']=$file;
    require $file; return;
}
$types=['css'=>'text/css','js'=>'text/javascript','woff'=>'font/woff','woff2'=>'font/woff2','png'=>'image/png','svg'=>'image/svg+xml','ico'=>'image/x-icon'];
if(!str_starts_with($relative,'assets/') || !isset($types[$extension])) { http_response_code(404); exit('Not found'); }
header('Content-Type: '.$types[$extension]);header('X-Content-Type-Options: nosniff');readfile($file);
