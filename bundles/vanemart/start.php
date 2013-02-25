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
  $view->styles = (array) $view['styles'];
  $view->classes = (array) $view['classes'];

  if (Request::$route and $name = array_get(Request::$route->action, 'as')) {
    $view->data['classes'][] = preg_replace('/[^\w\-]+/', '-', $name);
  }
});
