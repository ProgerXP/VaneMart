<?php namespace VaneMart;

use Vane\MenuItem;

class Block_MenuHandlers extends BaseBlock {
  static function menu_cart(MenuItem $item) {
    $item->url = action('vanemart::cart');
    $item->classes[] = 'cart';

    $key = Cart::has() ? 'cart_filled' : 'cart';
    $item->caption = \Vane\Menu::caption($key);

    if (Cart::has()) {
      $item->caption = Str::format($item->caption, array(
        'count'             => Str::langNum(__('vanemart::general.price'), Cart::count()),
        'sum'               => Str::langNum(__('vanemart::general.qty'), Cart::subtotal()),
      ));
    }
  }

  static function menu_categories(MenuItem $item) {
    $type = array_get($item->argArray(), 0, 'goods');

    $groups = Group
      ::where('type', '=', $type)
      ->where_null('parent')
      ->order_by('sort')
      ->get();

    foreach ($groups as $group) {
      $item->menu->add(new $item(array(
        'caption'         => $group->title,
        'classes'         => array('id-'.$group->id),
        'url'             => $group->url(),
      )));
    }
  }
}