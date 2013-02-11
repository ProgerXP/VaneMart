<?php namespace Vane;

// Handler for the standard 'vane::logo' block.
class Block_Logo extends Block {
  public $bundle = 'vane';

  function get_index() {
    return true;
  }
}