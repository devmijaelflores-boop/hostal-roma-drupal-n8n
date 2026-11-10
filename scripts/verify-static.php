<?php
// Sintaxis de archivos propios y coherencia básica de las rutas/servicios YAML.
require __DIR__ . '/../drupal/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
$root=dirname(__DIR__);$count=0;
foreach (['drupal/web/modules/custom','drupal/web/themes/custom','docker'] as $folder) {
 foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder,FilesystemIterator::SKIP_DOTS)) as $file) {
  if(in_array($file->getExtension(),['yml','yaml'],TRUE)) {Yaml::parseFile($file->getPathname());$count++;}
 }
}
echo "YAML de módulos, tema y Docker: $count archivos válidos.\n";
