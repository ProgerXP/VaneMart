<?php

if (!Bundle::exists('vane')) {
  throw new Error('VaneMart requires Vane bundle installed.');
} else {
  Bundle::start('vane');
  Vane\Current::set('vanemart::');
}

\Autoloader::namespaces(array('VaneMart' => __DIR__.DS.'classes'));
