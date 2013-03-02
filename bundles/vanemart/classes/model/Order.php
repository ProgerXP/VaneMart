<?php namespace VaneMart;

class Order extends BaseModel {
  static $table = 'orders';

  static function createBy(User $user, array $info) {
    static $fields = array('name', 'surname', 'address', 'phone', 'notes');

    $password = Str::password(array(
      'length'            => 10,
      'symbols'           => 0,
      'capitals'          => 3,
      'digits'            => 3,
    ));

    $order = with(new static)
      ->fill_raw(array_intersect_key($info, array_flip($fields)))
      ->fill_raw(array(
        'user'            => $user->id,
        'password'        => $password,
        'sum'             => Cart::subtotal(),
        'ip'              => Request::ip(),
      ));

    if (!$order->save()) {
      throw new Error('Cannot insert new order record.');
    }

    return $order;
  }

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