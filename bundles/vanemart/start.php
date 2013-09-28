<?php

if (!Bundle::exists('vane')) {
  throw new Error('VaneMart requires Vane bundle installed.');
} else {
  Bundle::start('vane');
  Vane\aliasIn('VaneMart');
}

Squall\initEx('VaneMart');

Vane\overrideHTMLki('vanemart::', array(
  'ns'                    => 'VaneMart',
  'stickyFormHiddens'     => array('back'),
));

require_once __DIR__.DS.'core.php';
require_once __DIR__.DS.'events.php';

Vane\Current::set(VaneMart\VANE_NS);

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

  $view->data += array('headEnd' => '', 'bodyEnd' => '');
  $view->classes = (array) $view['classes'];

  if (Request::$route and $name = array_get(Request::$route->action, 'as')) {
    $view->data['classes'][] = preg_replace('/[^\w\-]+/', '-', $name);
  }
});

View::composer('vanemart::mail.full', function ($view) {
  if (isset($view->mail)) {
    $view->mail->styleLocal('vanemart::mail.full');
    // new value of 'styles' set by styleLocal() won't be passed to this view
    // because it's already being rendered when the composer is called.
    $view->data['styles'] = $view->mail->view->data['styles'];
  }
});

Validator::register('vanemart_phone', function($attribute, $value, $parameters)
{
    return ltrim($value, '0..9()- ') === '';
});