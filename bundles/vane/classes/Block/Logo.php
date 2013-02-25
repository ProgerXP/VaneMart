<?php namespace Vane;

// Handler for the standard 'vane::logo' block.
class Block_Logo extends Block {
  public $bundle = 'vane';

  function get_index() {
    $info = Current::config('company');

    if (isset($this->input)) {
      // set null in place of missing values to avoid "Undefined variable" in view.
      $info = $this->input + S::combine($info, null);
    }

    $phone = head((array) $info['landline']) ?: head((array) $info['cellular']);
    return ((array) $info) + array('contactsURL' => null, 'phone' => $phone);
  }
}