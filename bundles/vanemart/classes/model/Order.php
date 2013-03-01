<?php namespace VaneMart;

class Order extends Eloquent {
  static $table = 'orders';

  function goods() {
    return $this->has_many(NS.'OrderProduct', 'order');
  }

  function user() {
    return $this->has_one(NS.'User', 'user');
  }

  function manager() {
    return $this->has_one(NS.'User', 'manager');
  }
}
Order::$table = \Config::get('vanemart::general.table_prefix').Order::$table;