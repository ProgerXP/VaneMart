<?php namespace Vane;

class Str extends \Px\Str {
  static $defaultNumber;

  // Unlike default $strings can be a Lang object or a string - passed to Lang::line().
  static function langNum($strings, $number, $html = false) {
    is_scalar($strings) and $strings = Current::lang($strings);
    return parent::langNum($strings, $number, $html);
  }
}

Str::$defaultNumber = array(
  'point'                 => Current::lang('general.number.point', '.'),
  'thousands'             => Current::lang('general.number.thousands', ' '),
) + \Px\Str::$defaultNumber;
