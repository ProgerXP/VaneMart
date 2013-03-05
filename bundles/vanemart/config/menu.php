<?php

if (Auth::guest()) {
  $user = array('login' => 'vanemart::login', 'register' => 'vanemart::register');
} else {
  $user = array('Vane::user', 'orders' => 'vanemart::orders', 'logout' => 'vanemart::logout');
}

return compact('user') + array(
  'cart'                  => array('VaneMart::cart', 'checkout' => 'vanemart::checkout'),

  'main'                  => array(
    'help' => 'vanemart::help', 'delivery' => 'help/delivery', 'wholesale' => 'help/opt',
    'contacts' => 'vanemart::contacts',
  ),

  'groups'                => 'VaneMart::categories',
);