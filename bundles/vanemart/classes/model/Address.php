<?php namespace VaneMart;

class Address extends BaseModel {
  static $table = 'addresses';

  function user() {
    return $this->belongs_to(NS.'User', 'user');
  }
}
Address::$table = \Config::get('vanemart::general.table_prefix').Address::$table;