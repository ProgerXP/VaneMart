<?php namespace Vane;

// Handler for the standard 'vane::status' block that simply returns current
// session 'status' value, if any.
class Block_Status extends Block {
  public $bundle = 'vane';

  function get_index($var = 'status') {
    return array('text' => \Session::get($var));
  }
}