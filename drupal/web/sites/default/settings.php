<?php

// Los secretos se inyectan en el contenedor, nunca se guardan en este archivo.
$required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DRUPAL_HASH_SALT'];
foreach ($required as $key) {
  if (!getenv($key) || getenv($key) === 'CHANGE_ME') {
    throw new \RuntimeException('Configuración requerida ausente: ' . $key);
  }
}
$databases['default']['default'] = [
  'database' => getenv('DB_NAME'),
  'username' => getenv('DB_USER'),
  'password' => getenv('DB_PASSWORD'),
  'host' => getenv('DB_HOST'),
  'port' => getenv('DB_PORT') ?: '3306',
  'driver' => 'mysql',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
  'prefix' => '',
];
$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT');
$trusted_hosts = array_unique([...array_filter(array_map('trim', explode(',', getenv('ROMA_TRUSTED_HOSTS') ?: 'localhost,127.0.0.1'))), 'drupal']);
$settings['trusted_host_patterns'] = array_map(static fn(string $host): string => '^' . preg_quote($host, '/') . '$', $trusted_hosts);
$trusted_proxies = array_filter(array_map('trim', explode(',', getenv('ROMA_REVERSE_PROXY_ADDRESSES') ?: '')));
if ($trusted_proxies) {
  $settings['reverse_proxy'] = TRUE;
  $settings['reverse_proxy_addresses'] = array_values($trusted_proxies);
  $settings['reverse_proxy_trusted_headers'] = \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_FOR
    | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_HOST
    | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PORT
    | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PROTO;
}
$settings['config_sync_directory'] = '../config/sync';
$settings['file_scan_ignore_directories'] = ['node_modules', 'bower_components'];
$settings['update_free_access'] = FALSE;
// No se habilita CORS global. La integración del chat se definirá en fase 5.
