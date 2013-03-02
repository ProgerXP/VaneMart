<?php

if (!Bundle::exists('vane')) {
  throw new Error('VaneMart requires Vane bundle installed.');
} else {
  Bundle::start('vane');
  Vane\Current::set('vanemart::');
}

Px\Plarx::supersede('VaneMart');
Squall\initEx('VaneMart');
Vane\overrideHTMLki('vanemart::', 'VaneMart');

require_once __DIR__.DS.'core.php';

// More flexible autoloader for VaneMart flat class structure.
spl_autoload_register(function ($class) {
  $base = $file = __DIR__.DS.'classes'.DS;

  if (substr($class, 0, 9) === 'VaneMart\\') {
    @list($group, $rest) = explode('_', substr($class, 9), 2);

    if (isset($rest)) {
      $file .= strtolower($group).DS.$rest;
    } else {
      $file .= is_file("$file$group.php") ? $group : 'model'.DS.substr($class, 9);
    }
  } else {
    $file .= 'vendor'.DS.$class;
  }

  $file .= '.php';
  is_file($file) and (include $file);
});

View::composer('vanemart::full', function ($view) {
  $normAssets = function ($type) use ($view) {
    $items = (array) $view[$type];
    $normal = array();

    foreach ($items as $key => $attributes) {
      is_int($key) ? $normal[$attributes] = array() : $normal[$key] = $attributes;
    }

    $view[$type] = $normal;

    $html = &$view->data[ 'html'.ucfirst($type) ];
    $html .= Asset::$type();
  };

  $normAssets('styles');
  $normAssets('scripts');

  $view->classes = (array) $view['classes'];

  if (Request::$route and $name = array_get(Request::$route->action, 'as')) {
    $view->data['classes'][] = preg_replace('/[^\w\-]+/', '-', $name);
  }
});
