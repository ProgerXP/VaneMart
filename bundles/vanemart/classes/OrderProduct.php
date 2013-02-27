<?php namespace VaneMart;

class OrderProduct extends Eloquent {
  static $table = 'order_goods';

  function order() {
    return $this->belongs_to(NS.'Order', 'order');
  }
}
OrderProduct::$table = \Config::get('vanemart::general.table_prefix').OrderProduct::$table;