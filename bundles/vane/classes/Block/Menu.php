<?php namespace Vane;

// Handler for the standard 'vane::menu' block.
class Block_Menu extends Block {
  public $bundle = 'vane';

  function get_index($name = 'main') {
    return Menu::fromConfig($name)->toArray();
  }
}