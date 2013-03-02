<?php namespace VaneMart;

class BaseBlock extends \Vane\Block {
  public $bundle = 'vanemart';

  static function back($default = '/') {
    return Redirect::back(Input::get('back', $default));
  }
}