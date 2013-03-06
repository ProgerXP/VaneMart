<?php namespace VaneMart;

use Vane\MenuItem;

class Block_MenuHandlers extends BaseBlock {
  static function menu_cart(MenuItem $item) {
    $item->url = route('vanemart::cart');
    $item->classes[] = 'cart';

    $key = Cart::has() ? 'cart_filled' : 'cart';
    $item->caption = \Vane\Menu::caption($key);

    if (Cart::has()) {
      $sum = Cart::subtotal();

      $replaces = array(
        'sumn'              => Str::number($sum),
        'summ'              => Str::langNum('general.price', $sum),
        'sumf'              => Str::langNum('general.currency_full', $sum),
        'sums'              => Str::langNum('general.currency_short', $sum),
        'count'             => Str::langNum('general.goods', Cart::count()),
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

    $current = static::detectCurrentGroup($groups);

    foreach ($groups as $group) {
      $item->menu->add(new $item(array(
        'caption'         => $group->title,
        'classes'         => array('id-'.$group->id),
        'url'             => $group->url(),
        'current'         => ($current and $current->id === $group->id) ? true : null,
      )));
    }
  }

  static function detectCurrentGroup(array $topLevel) {
    $route = \Vane\Route::current();

    if ($route and $route->lastServer instanceof Block_Product and
        $route->lastServer->product) {
      $id = $route->lastServer->product->group;

      $finder = function ($key, $group) use ($id) { return $group->id == $id; };
      $current = array_first($topLevel, $finder);

      if (!$current) {
        // current group is not top-level (parent IS NULL) so we need to trace
        // it up to the root group.
        $current = Group::find($id)->root();
      }

      return $current;
    }
  }
}