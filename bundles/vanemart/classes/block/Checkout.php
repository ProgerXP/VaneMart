<?php namespace VaneMart;

class Block_Checkout extends BaseBlock {
  static function register(array $info) {
    static $fields = array('name', 'surname', 'phone', 'notes', 'email');

    $model = User::where('email', '=', $info['email'])->first();

    if (!$model) {
      $password = Str::password(\Config::get('vanemart::password'));

      $model = with(new User)
        ->fill_raw(array_intersect_key($info, array_flip($fields)))
        ->fill_raw(array(
          'password'      => $password,
          'reg_ip'        => Request::ip(),
        ));

      if (!$model->save()) {
        throw new Error('Cannot register new user on checkout.');
      }
    }

    return $model;
  }

  static function addOrder(User $user, array $info) {
    $password = Str::password(array(
      'length'            => 10,
      'symbols'           => 0,
      'capitals'          => 3,
      'digits'            => 3,
    ));

    $fields = array('name', 'surname', 'address', 'phone', 'notes');

    $order = with(new Order)
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

  function get_index() {
    return true;
  }

  function post_index() {
    if (!Cart::has()) {
      return static::back();
    } elseif ($min = Cart::isTooSmall()) {
      $status = HLEx::lang('vanemart::checkout.small', array(
        'min'     => HLEx::langNum(__('vanemart::general.price'), $min),
        'total'   => HLEx::langNum(__('vanemart::general.price'), Cart::subtotal()),
      ));

      return static::back()->with(copact('status'));
    }

    $result = $this->ajax();

    if ($result instanceof Order) {
      return Redirect::to(route('vanemart::order', $result->id).
                          '?code='.urlencode($result->password));
    } else {
      return $result;
    }
  }

  function ajax_post_index() {
    $valid = Validator::make(Input::get(), array(
      'email'           => 'required|email',
      'name'            => 'required',
      'surname'         => 'required',
      'address'         => 'min:5',
      'phone'           => 'required|min:7',
    ));

    if (!Cart::has() or Cart::isTooSmall()) {
      return E_INPUT;
    } elseif ($valid->fails()) {
      return $valid;
    } else {
      $user = static::register(Input::get());
      $self = $this;

      \DB::transaction(function () use ($self, $user, &$order) {
        $order = $self::addOrder($user, Input::get());

        $goods = S::map(Cart::all(), function ($qty, $product) use ($order) {
          return compact('qty', 'product') + array('order' => $order->id);
        });

        OrderProduct::insert($goods);
      });

      return $order;
    }
  }
}