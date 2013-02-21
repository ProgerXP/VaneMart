<?php namespace VaneMart;

class Group extends Eloquent {
  static $table = 'groups';

  static function prettyOther(array $attrs) {
    foreach ($attrs as $name => &$value) {
      switch ($name) {
      case 'title':   $value = typography($value); break;
      }
    }

    return $attrs;
  }

  function pretty() {
    return $this->fill_raw( static::prettyOther($this->to_array()) );
  }
}
Group::$table = \Bundle::option('vanemart', 'table_prefix', 'vm_').Group::$table;