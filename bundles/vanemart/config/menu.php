<?php

if (Auth::guest()) {
  $user = array('login' => 'vanemart::login');
} else {
  $user = array('orders' => 'vanemart::orders', 'logout' => 'vanemart::logout');
}

$cart = array('VaneMart::cart');
VaneMart\Cart::has() and $cart['checkout'] = 'vanemart::checkout';

return compact('user', 'cart') + array(
  'main' => array(
    'search' => 'vanemart::search',
    'help' => 'vanemart::help', 'delivery' => 'help/delivery',
    'wholesale' => 'help/opt', 'contacts' => 'vanemart::contacts',
  ),

  'groups' => 'VaneMart::categories',
);