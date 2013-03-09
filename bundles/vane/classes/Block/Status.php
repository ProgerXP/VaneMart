<?php namespace Vane;

// Handler for the standard 'vane::status' block that simply returns current
// session 'status' value, if any. Be careful - HTML entities are preserved.
class Block_Status extends Block {
  public $bundle = 'vane';

  function get_index($var = 'status') {
    $html = trim(\Session::get($var));

    if ($html === '') {
      return '';
    } else {
      return compact('html');
    }
  }
}