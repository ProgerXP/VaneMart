<?php namespace VaneMart;

use \Vane\MenuItem;

class Block_MenuHandlers extends BaseBlock {
  static function menu_cart(MenuItem $item) {
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