<?php

if (Auth::guest()) {
  $user = array('login' => 'users/login', 'register' => 'users/reg');
} else {
  $user = array('VaneMart:user', 'orders' => 'orders');
}

return $user + array(
  'cart'                  => array('VaneMart::cart', 'checkout' => 'cart/checkout'),

  'main'                  => array(
    'help' => 'help', 'delivery' => 'help/delivery', 'wholesale' => 'help/opt',
    'contacts' => 'help/contacts',
  ),

  'groups'                => 'VaneMart::categories',
);