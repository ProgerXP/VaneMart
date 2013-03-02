<?php namespace VaneMart;

use Vane\MenuItem;

class Block_MenuHandlers extends BaseBlock {
  static function menu_cart(MenuItem $item) {
    $item->url = action('vanemart::cart');
    $item->classes[] = 'cart';

    $key = Cart::has() ? 'cart_filled' : 'cart';
    $item->caption = \Vane\Menu::caption($key);

    if (Cart::has()) {
      $sum = Cart::subtotal();

      $replaces = array(
        'sumn'              => Str::number($sum),
        'summ'              => Str::langNum(__('vanemart::general.price'), $sum),
        'sumf'              => Str::langNum(__('vanemart::general.currency_full'), $sum),
        'sums'              => Str::langNum(__('vanemart::general.currency_short'), $sum),
        'count'             => Str::langNum(__('vanemart::general.goods'), Cart::count()),
      );

      $item->caption = Str::format($item->caption, $replaces);
      $item->hint = Str::format(\Vane\Menu::caption('cart_hint'), $replaces);
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