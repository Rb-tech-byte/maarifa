<?php
// Lightweight cache helpers: APCu if available, fallback to filesystem in /tmp or project cache dir
function ak_cache_enabled(): bool {
  return function_exists('apcu_enabled') ? apcu_enabled() : function_exists('apcu_fetch');
}

function ak_cache_get(string $key)
{
  $ns = 'ak23_';
  if (function_exists('apcu_fetch')) {
    $ok = false; $val = apcu_fetch($ns.$key, $ok);
    if ($ok) return $val;
  }
  $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $ns . md5($key) . '.cache';
  if (is_file($file) && (filemtime($file) + 3600) > time()) {
    $raw = @file_get_contents($file);
    if ($raw !== false) { $val = @unserialize($raw); return $val; }
  }
  return null;
}

function ak_cache_set(string $key, $value, int $ttl = 300): bool {
  $ns = 'ak23_';
  $ok = false;
  if (function_exists('apcu_store')) {
    $ok = apcu_store($ns.$key, $value, $ttl);
  }
  $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $ns . md5($key) . '.cache';
  @file_put_contents($file, serialize($value));
  @touch($file, time());
  return $ok || true;
}
