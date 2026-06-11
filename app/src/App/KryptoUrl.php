<?php

/**
 * Canonical URL helpers for security-sensitive redirects and provider callbacks.
 *
 * @package Krypto
 */
class KryptoUrl {

  public static function canonicalBaseUrl($appUrl = null, $requireHttps = false){
    if(is_null($appUrl)){
      if(!defined('APP_URL')) throw new InvalidArgumentException('APP_URL is not configured.');
      $appUrl = APP_URL;
    }

    $appUrl = trim((string) $appUrl);
    if($appUrl == '' || preg_match('/[\r\n]/', $appUrl)) throw new InvalidArgumentException('Invalid APP_URL.');

    $parts = parse_url($appUrl);
    if(!is_array($parts) || !isset($parts['scheme']) || !isset($parts['host'])){
      throw new InvalidArgumentException('APP_URL must be an absolute URL.');
    }
    if(isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])){
      throw new InvalidArgumentException('APP_URL must be a base URL without credentials, query, or fragment.');
    }

    $scheme = strtolower((string) $parts['scheme']);
    if(!in_array($scheme, ['http', 'https'], true)) throw new InvalidArgumentException('APP_URL scheme must be http or https.');
    if($requireHttps && $scheme != 'https') throw new InvalidArgumentException('Payment callback APP_URL must use https.');

    $authority = self::authorityFromHostAndPort($parts['host'], (isset($parts['port']) ? $parts['port'] : null), $scheme);
    $path = (isset($parts['path']) ? self::basePath($parts['path']) : '');

    return $scheme.'://'.$authority.$path;
  }

  public static function canonicalScheme($appUrl = null){
    $parts = parse_url(self::canonicalBaseUrl($appUrl));
    return strtolower((string) $parts['scheme']);
  }

  public static function canonicalHost($appUrl = null){
    $baseUrl = self::canonicalBaseUrl($appUrl);
    $parts = parse_url($baseUrl);
    return self::authorityFromHostAndPort($parts['host'], (isset($parts['port']) ? $parts['port'] : null), $parts['scheme']);
  }

  public static function requestMatchesCanonicalHost($server, $appUrl = null){
    if(!is_array($server)) $server = [];

    $requestHost = self::normalizeHostHeader(
      (isset($server['HTTP_HOST']) ? $server['HTTP_HOST'] : ''),
      self::canonicalScheme($appUrl)
    );

    return $requestHost != '' && $requestHost === self::canonicalHost($appUrl);
  }

  public static function requestScheme($server){
    if(!is_array($server)) $server = [];

    if(isset($server['HTTPS']) && $server['HTTPS'] !== '' && strtolower((string) $server['HTTPS']) !== 'off') return 'https';
    if(isset($server['SERVER_PORT']) && (string) $server['SERVER_PORT'] === '443') return 'https';

    foreach (['HTTP_X_FORWARDED_PROTO', 'HTTP_X_FORWARDED_SCHEME', 'REQUEST_SCHEME'] as $key) {
      if(!isset($server[$key])) continue;
      $value = strtolower(trim(explode(',', (string) $server[$key])[0]));
      if(in_array($value, ['http', 'https'], true)) return $value;
    }

    if(isset($server['HTTP_X_FORWARDED_SSL']) && strtolower((string) $server['HTTP_X_FORWARDED_SSL']) === 'on') return 'https';
    return 'http';
  }

  public static function canonicalUrlForRequest($server, $appUrl = null){
    if(!is_array($server)) $server = [];

    $baseUrl = self::canonicalBaseUrl($appUrl);
    $path = self::requestScriptPath($server, $baseUrl);
    $queryString = self::requestQueryString($server);

    return $baseUrl.$path.($queryString != '' ? '?'.$queryString : '');
  }

  public static function paymentCallbackUrl($path, $query = [], $appUrl = null){
    return self::buildUrl($path, $query, $appUrl, true);
  }

  public static function buildUrl($path, $query = [], $appUrl = null, $requireHttps = false){
    $url = self::canonicalBaseUrl($appUrl, $requireHttps);
    $path = self::safePath($path);
    if($path != '/') $url .= $path;

    $queryString = self::buildQueryString($query);
    return $url.($queryString != '' ? '?'.$queryString : '');
  }

  private static function requestScriptPath($server, $baseUrl){
    $path = '';
    foreach (['SCRIPT_NAME', 'SCRIPT_URL', 'REQUEST_URI'] as $key) {
      if(!isset($server[$key]) || (string) $server[$key] === '') continue;
      $path = (string) $server[$key];
      if($key == 'REQUEST_URI'){
        $parsedPath = parse_url($path, PHP_URL_PATH);
        $path = (is_string($parsedPath) && $parsedPath != '' ? $parsedPath : '/');
      }
      break;
    }

    $path = self::safePath($path);

    $baseParts = parse_url($baseUrl);
    $basePath = (isset($baseParts['path']) ? self::safePath($baseParts['path']) : '/');
    if($basePath != '/' && $path === $basePath) return '/';
    if($basePath != '/' && strpos($path, $basePath.'/') === 0) return substr($path, strlen($basePath));

    return $path;
  }

  private static function requestQueryString($server){
    if(!isset($server['QUERY_STRING'])) return '';
    $queryString = (string) $server['QUERY_STRING'];
    if($queryString == '' || preg_match('/[\r\n]/', $queryString)) return '';
    return ltrim($queryString, '?');
  }

  private static function buildQueryString($query){
    if(is_array($query)){
      if(count($query) == 0) return '';
      return http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    $query = (string) $query;
    if($query == '' || preg_match('/[\r\n]/', $query)) return '';
    return ltrim($query, '?');
  }

  private static function basePath($path){
    $path = self::safePath($path);
    if($path == '/') return '';
    return rtrim($path, '/');
  }

  private static function safePath($path){
    $path = (string) $path;
    if($path == '' || preg_match('/[\r\n]/', $path)) return '/';

    $parsedPath = parse_url($path, PHP_URL_PATH);
    if(is_string($parsedPath) && $parsedPath != '') $path = $parsedPath;

    $path = str_replace('\\', '/', $path);
    if($path == '' || $path[0] != '/') $path = '/'.$path;
    return preg_replace('#/+#', '/', $path);
  }

  private static function normalizeHostHeader($hostHeader, $scheme = null){
    $hostHeader = trim((string) $hostHeader);
    if($hostHeader == '' || preg_match('/[\r\n\/\\\\@]/', $hostHeader)) return '';

    $host = $hostHeader;
    $port = null;

    if($hostHeader[0] == '['){
      if(!preg_match('/^\[([^\]]+)\](?::([0-9]{1,5}))?$/', $hostHeader, $matches)) return '';
      $host = $matches[1];
      if(!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return '';
      if(isset($matches[2]) && $matches[2] !== '') $port = $matches[2];
    } elseif(substr_count($hostHeader, ':') == 1) {
      $separatorPosition = strrpos($hostHeader, ':');
      $possiblePort = substr($hostHeader, $separatorPosition + 1);
      if(preg_match('/^[0-9]{1,5}$/', $possiblePort)){
        $host = substr($hostHeader, 0, $separatorPosition);
        $port = $possiblePort;
      }
    }

    try {
      return self::authorityFromHostAndPort($host, $port, $scheme);
    } catch (InvalidArgumentException $e) {
      return '';
    }
  }

  private static function authorityFromHostAndPort($host, $port = null, $scheme = null){
    $host = self::normalizeHost($host);
    if($host == '') throw new InvalidArgumentException('Invalid host.');

    $authority = (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '['.$host.']' : $host);

    if(!is_null($port) && $port !== ''){
      if(!preg_match('/^[0-9]{1,5}$/', (string) $port)) throw new InvalidArgumentException('Invalid port.');
      $port = (int) $port;
      if($port < 1 || $port > 65535) throw new InvalidArgumentException('Invalid port.');
      if(!self::isDefaultPort($scheme, $port)) $authority .= ':'.$port;
    }

    return $authority;
  }

  private static function normalizeHost($host){
    $host = trim((string) $host);
    if($host == '' || preg_match('/[\r\n\/\\\\@]/', $host)) return '';

    if($host[0] == '['){
      if(substr($host, -1) != ']') return '';
      $host = substr($host, 1, -1);
      if(!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return '';
      return strtolower($host);
    }
    if(strpos($host, '[') !== false || strpos($host, ']') !== false) return '';

    if(filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return strtolower($host);
    if(filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return $host;

    $host = strtolower(rtrim($host, '.'));
    if($host == 'localhost') return $host;
    if(!preg_match('/^[a-z0-9.-]+$/', $host)) return '';
    if(strpos($host, '..') !== false) return '';

    foreach (explode('.', $host) as $label) {
      if($label == '' || $label[0] == '-' || substr($label, -1) == '-') return '';
    }

    return $host;
  }

  private static function isDefaultPort($scheme, $port){
    $scheme = strtolower((string) $scheme);
    return ($scheme == 'http' && (int) $port == 80) || ($scheme == 'https' && (int) $port == 443);
  }
}

?>
