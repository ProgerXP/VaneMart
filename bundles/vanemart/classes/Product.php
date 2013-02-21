<?php namespace VaneMart;

class Product extends Eloquent {
  static $table = 'goods';

  static function prettyOther(array $attrs) {
    foreach ($attrs as $name => &$value) {
      switch ($name) {
      case 'title':
      case 'group':   $value = typography($value); break;
      case 'desc':    $value = prettyText($value); break;
      case 'country': $value = S::capitalize(trim($value)); break;
      }
    }

    return $attrs;
  }

  function pretty() {
    return $this->fill_raw( static::prettyOther($this->to_array()) );
  }
}
Product::$table = \Bundle::option('vanemart', 'table_prefix', 'vm_').Product::$table;