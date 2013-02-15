<?php namespace Vane;

// Holds standard menu item handlers.
class Block_MenuHandlers extends Block {
  public $bundle = 'vane';

  static function menu_user(MenuItem $item) {
    $item->caption = Str::format(Menu::caption('user'), \Auth::user());
  }
}