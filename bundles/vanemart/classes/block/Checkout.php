<?php namespace VaneMart;

class Block_Checkout extends BaseBlock {
  protected function init() {
    $this->filter('before', 'csrf')->on('post');
  }

  function prereq() {
    if (!Cart::has()) {
      return static::back();
    } elseif ($this->can('checkout.deny')) {
      return false;
    } elseif ($min = Cart::isTooSmall()) {
      $this->status('small', array(
        'min'     => Str::langNum('general.price', $min),
        'total'   => Str::langNum('general.price', Cart::subtotal()),
      ));

      return static::back();
    }
  }

  /*---------------------------------------------------------------------
  | GET checkout/index
  |
  | Outputs form for checking out.
  |--------------------------------------------------------------------*/
  function get_index() {
    if ($response = $this->prereq()) {
      return $response;
    } else {
      return Cart::has() ? true : static::back();
    }
  }

  /*---------------------------------------------------------------------
  | POST checkout/index
  |
  | Places the order.
  |----------------------------------------------------------------------
  | * csrf=CSRF     - REQUIRED.
  |--------------------------------------------------------------------*/
  function post_index() {
    if ($response = $this->prereq()) {
      return $response;
    } else {
      $result = $this->ajax();

      if ($result instanceof Order) {
        return Redirect::to($result->url());
      } else {
        return $result;
      }
    }
  }

  function ajax_post_index() {
    $input = $this->in();
    $valid = Validator::make($input, array(
      'email'           => 'required|email',
      'name'            => 'required',
      'surname'         => 'required',
      'address'         => 'min:5',
      'phone'           => 'required|min:7',
    ));

    if ($this->can('checkout.deny')) {
      return false;
    } elseif (!Cart::has() or Cart::isTooSmall()) {
      return E_INPUT;
    } elseif ($valid->fails()) {
      return $valid;
    } else {
      $user = User::findOrCreate($input);
      $order = null;

      \DB::transaction(function () use (&$input, $user, &$order) {
        $order = Order::createBy($user, $input);

        $goods = S(Cart::all(), function ($qty, $product) use ($order) {
          return compact('qty', 'product') + array('order' => $order->id);
        });

        OrderProduct::insert($goods);
      });

      Cart::clear();
      return $order;
    }
  }
}