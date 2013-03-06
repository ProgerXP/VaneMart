<?php namespace VaneMart;

class Block_Checkout extends BaseBlock {
  protected function init() {
    $this->filter('before', 'csrf')->only(array('add', 'set'));
  }

  /*---------------------------------------------------------------------
  | GET checkout/index
  |
  | Outputs form for checking out.
  |--------------------------------------------------------------------*/
  function get_index() {
    return true;
  }

  /*---------------------------------------------------------------------
  | POST checkout/index
  |
  | Places the order.
  |----------------------------------------------------------------------
  | * csrf=CSRF     - REQUIRED.
  |--------------------------------------------------------------------*/
  function post_index() {
    if (!Cart::has()) {
      return static::back();
    } elseif ($min = Cart::isTooSmall()) {
      $status = HLEx::lang('vanemart::checkout.small', array(
        'min'     => HLEx::langNum('general.price', $min),
        'total'   => HLEx::langNum('general.price', Cart::subtotal()),
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
      $user = User::findOrCreate(Input::get());
      $order = null;

      \DB::transaction(function () use ($user, &$order) {
        $order = Order::createBy($user, Input::get());

        $goods = S(Cart::all(), function ($qty, $product) use ($order) {
          return compact('qty', 'product') + array('order' => $order->id);
        });

        OrderProduct::insert($goods);
      });

      return $order;
    }
  }
}