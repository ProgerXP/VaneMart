<?php namespace VaneMart;

class Order extends BaseModel {
  static $table = 'orders';
  static $hasURL = true;

  // Used by 'order.change_lines' event. 'manager' is present to avoid showing
  // internal company operations in the public.
  static $ignoreLogFields = array('manager', 'created_at', 'updated_at');

  //= array of str 'new', 'paid', etc.
  static function statuses() {
    return array_keys((array) __('vanemart::order.status')->get());
  }

  //= str
  static function generatePassword() {
    return (string) Event::result('order.new.password', function ($password) {
      return strlen($password) < 1 ? 'a blank string' : true;
    });
  }

  static function createBy(User $user, array $info) {
    static $fields = array('name', 'surname', 'city', 'address', 'phone', 'notes');

    $order = with(new static)
      ->fill_raw(array_intersect_key($info, array_flip($fields)))
      ->fill_raw(array(
        'password'        => static::generatePassword(),
        'user'            => $user->id,
        'manager'         => \Vane\Current::config('general.new_order_manager'),
        'sum'             => Cart::subtotal(),
        'ip'              => Request::ip(),
      ));

    return Event::insertModel($order, 'order');
  }

  function regeneratePassword() {
    $this->password = static::generatePassword();
    return $this;
  }

  function url() {
    return route('vanemart::order', $this->id).'?code='.$this->password;
  }

  function goods() {
    return $this->has_many(NS.'OrderProduct', 'order');
  }

  function user() {
    return $this->belongs_to(NS.'User', 'user');
  }

  function manager() {
    return $this->belongs_to(NS.'User', 'manager');
  }

  function posts() {
    return $this->has_many(NS.'Post', 'post')->where('type', '=', 'order');
  }

  function changeMessages() {
    $result = array();
    Event::fire('order.change_lines', array(&$result, $this));
    return $result;
  }

  function isOf(User $user = null) {
    return Event::until('order.belongs', array($this, $user));
  }
}
Order::$table = \Config::get('vanemart::general.table_prefix').Order::$table;