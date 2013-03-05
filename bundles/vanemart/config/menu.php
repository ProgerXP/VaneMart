<?php

if (Auth::guest()) {
  $user = array('login' => 'user/login', 'register' => 'user/reg');
} else {
  $user = array('Vane::user', 'orders' => 'orders', 'logout' => 'user/logout');
}

return compact('user') + array(
  'cart'                  => array('VaneMart::cart', 'checkout' => 'checkout'),

  'main'                  => array(
    'help' => 'help', 'delivery' => 'help/delivery', 'wholesale' => 'help/opt',
    'contacts' => 'help/contacts',
  ),

  'groups'                => 'VaneMart::categories',
);