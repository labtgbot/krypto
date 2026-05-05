<?php

if(!function_exists('krypto_path_is_absolute')){
  function krypto_path_is_absolute($path){
    if(!is_string($path) || $path === '') return false;
    if($path[0] === '/' || $path[0] === '\\') return true;
    return preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
  }
}

if(!function_exists('krypto_trim_path')){
  function krypto_trim_path($path){
    if(!is_string($path)) return '';
    $path = rtrim($path, "/\\");
    return ($path === '' ? '/' : $path);
  }
}

if(!function_exists('krypto_join_path')){
  function krypto_join_path($base, $path = ''){
    $base = krypto_trim_path($base);
    $path = ltrim((string) $path, "/\\");
    if($path === '') return $base;
    return $base.'/'.$path;
  }
}

if(!function_exists('krypto_detect_app_root')){
  function krypto_detect_app_root(){
    $candidates = [];

    if(defined('FILE_PATH')){
      $filePath = krypto_trim_path(FILE_PATH);

      if(krypto_path_is_absolute($filePath)){
        $candidates[] = $filePath;
      }

      if(isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== ''){
        $candidates[] = krypto_join_path($_SERVER['DOCUMENT_ROOT'], $filePath);
      }
    }

    $candidates[] = dirname(__DIR__, 2);

    foreach ($candidates as $candidate) {
      if(is_dir($candidate)
        && file_exists(krypto_join_path($candidate, 'vendor/autoload.php'))
        && file_exists(krypto_join_path($candidate, 'app/src/App/App.php'))){
        return krypto_trim_path($candidate);
      }
    }

    return null;
  }
}

if(!function_exists('krypto_normalize_document_root')){
  function krypto_normalize_document_root(){
    if(!defined('FILE_PATH')) return;

    $root = krypto_detect_app_root();
    if(is_null($root)) return;

    if(!defined('KRYPTO_APP_ROOT')) define('KRYPTO_APP_ROOT', $root);

    $filePath = krypto_trim_path(FILE_PATH);
    if(krypto_path_is_absolute($filePath) && $root === $filePath){
      $_SERVER['DOCUMENT_ROOT'] = '';
    }
  }
}

if(!function_exists('krypto_app_path')){
  function krypto_app_path($path = ''){
    $root = (defined('KRYPTO_APP_ROOT') ? KRYPTO_APP_ROOT : krypto_detect_app_root());
    if(is_null($root)) {
      $root = (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '').(defined('FILE_PATH') ? FILE_PATH : '');
    }
    return krypto_join_path($root, $path);
  }
}

krypto_normalize_document_root();

?>
