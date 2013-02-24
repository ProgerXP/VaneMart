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

\Autoloader::directories(array(__DIR__.DS.'libraries'));
\Autoloader::namespaces(array('VaneMart' => __DIR__.DS.'classes'));

View::composer('vanemart::full', function ($view) {
  $styles = array();

  foreach ((array) $view->styles as $style => $attributes) {
    if (is_int($style)) {
      $style = VaneMart\asset($attributes);
      $attributes = array();
    } elseif (!is_array($attributes)) {
      $attributes = array('media' => $attributes);
    }

    $styles[$style] = $attributes;
  }

  $view->styles = $styles;
});
