<?php

if (!Bundle::exists('vane')) {
  throw new Error('VaneMart requires Vane bundle installed.');
} else {
  Bundle::start('vane');
  Vane\Current::set('vanemart::');
}

Px\Plarx::supersede('VaneMart');
Squall\initEx('VaneMart');

require_once __DIR__.DS.'core.php';
\Autoloader::namespaces(array('VaneMart' => __DIR__.DS.'classes'));
