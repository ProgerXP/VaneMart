<?php namespace Vane;

class HLEx extends \Px\HLEx {
  static function number($num, $options = null) {
    return Str::number($num, array('html' => true) + arrize($options, 'point'));
  }

  static function langNum($strings, $number) {
    return Str::langNum($strings, $number, true);
  }
}