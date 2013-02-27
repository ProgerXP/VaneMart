<?php namespace VaneMart;

class BaseBlock extends \Vane\Block {
  public $bundle = 'vanemart';

  static function back() {
    return Redirect::back(Input::get('back', '/'));
  }
}