<?php namespace Vane;

// Handler for the standard 'vane::menu' block.
class Block_Menu extends Block {
  public $bundle = 'vane';

  function get_index($name = 'main') {
    $menu = Menu::fromConfig($name);

    if (!count($menu)) {
      Log::warn_Menu("Empty menu [$name].");
    } elseif (Request::ajax()) {
      $items = S($menu)
        ->keep('?.visible')
        ->map(function ($item) {
          return S::omit(array_except($item->toArray(), 'visible'), '"?" === ""');
        })
        ->get();

      return compact('items');
    } else {
      return compact('menu');
    }
  }
}