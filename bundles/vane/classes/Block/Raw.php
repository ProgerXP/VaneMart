<?php namespace Vane;

// Block handler that simply returns its input. Shortcuts to '=input string'.
class Block_Raw extends Block {
  public $bundle = 'vane';

  function get_index() {
    return join( \Px\arrize($this->input) );
  }
}