<?php namespace Vane;

// Block handler that simply returns its input. Shortcuts to '=input string'.
class Block_Raw extends Block {
  public $bundle = 'vane';

  function get_index() {
    $join = function ($value) use (&$join) {
      if (is_array($value)) {
        return join(array_map($join, $value));
      } else {
        return (string) $value;
      }
    };

    return $join($this->input);
  }
}