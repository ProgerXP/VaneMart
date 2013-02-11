<?php namespace Vane;

class PublicBundle extends \Bundle {
  static function autoloads($bundle, $config) {
    return parent::autoloads($bundle, $config);
  }
}

class Error extends \Exception { }

if (!\Bundle::exists('plarx')) {
  throw new Error('Vane requires Plarx bundle installed.');
} else {
  \Bundle::start('plarx');
  \Px\Plarx::aliasIn(__NAMESPACE__);
}

\Autoloader::namespaces(array(__NAMESPACE__ => __DIR__.DS.'classes'));

if (!\Bundle::option('vane', 'autoloads') and is_file($config = __DIR__.DS.'bundle.php')) {
  PublicBundle::autoloads('vane', include $config);
}
