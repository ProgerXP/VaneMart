<?php namespace VaneMart;

class Order extends BaseModel {
  static $table = 'orders';
  static $hasURL = true;

  //= array of str 'new', 'paid', etc.
  static function statuses() {
    return array_keys((array) __('vanemart::order.status')->get());
  }

  //= str
  static function generatePassword() {
    return Str::password(array(
      'length'            => 10,
      'symbols'           => 0,
      'capitals'          => 3,
      'digits'            => 3,
    ));
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

    if (!$order->save()) {
      throw new Error('Cannot insert new order record.');
    }

    return $order;
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

    foreach ($this->get_dirty() as $field => $value) {
      if ($field === 'manager') { continue; }

      $vars = array(
        'field'       => __("vanemart::field.$field")->get(),
        'old'         => trim($this->original[$field]),
        'new'         => $value,
      );

      if ($field === 'status') {
        $vars['old'] = __("vanemart::order.status.$vars[old]")->get();
        $vars['new'] = __("vanemart::order.status.$vars[new]")->get();
      }

      $type = $vars['old'] === '' ? 'add' : ($value === '' ? 'delete' : 'set');
      $result[] = __("vanemart::order.set.line.$type", $vars);
    }

    return $result;
  }

  function isOf(User $user = null) {
    if ($user) {
      $field = $user->can('manager') ? 'manager' : 'user';
      return $this->{"get_$field"}() == $user->id;
    }
  }
}
Order::$table = \Config::get('vanemart::general.table_prefix').Order::$table;