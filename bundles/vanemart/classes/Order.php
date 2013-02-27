<?php namespace VaneMart;

class Order extends Eloquent {
  static $table = 'orders';

  function goods() {
    return $this->has_many(NS.'OrderProduct', 'order');
  }
}
Order::$table = \Config::get('vanemart::general.table_prefix').Order::$table;