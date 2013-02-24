<?php namespace Vane;

class Error extends \Exception { }

if (!\Bundle::exists('plarx')) {
  throw new Error('Vane requires Plarx bundle installed.');
} else {
  \Bundle::start('plarx');
  \Px\Plarx::supersede(__NAMESPACE__);
}

if (!\Bundle::exists('squall')) {
  throw new Error('Vane requires Squall bundle installed.');
} else {
  \Bundle::start('squall');
  \Squall\initEx(__NAMESPACE__);
}

\Autoloader::namespaces(array(__NAMESPACE__ => __DIR__.DS.'classes'));
