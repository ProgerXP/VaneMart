<?php namespace VaneMart;

class Block_Checkout extends BaseBlock {
  protected function init() {
    $this->filter('before', 'csrf')->on('post');
  }

  function prereq() {
    if (!Cart::has()) {
      return $this->back();
    } elseif ($this->can('checkout.deny')) {
      return false;
    } elseif (Event::until('checkout.can', $this) === false) {
      return $this->back();
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
      if ($user = $this->user(false)) {
        \Session::flash(Input::old_input, $user->attributes);
      }

      return Cart::has() ? true : $this->back();
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

    $rules = array(
      'email'           => 'required|email',
      'name'            => 'required',
      'surname'         => 'required',
      'address'         => 'min:5',
      'phone'           => 'required|min:7|vanemart_phone',
    );
    $rules += (array) \Vane\Current::config('general.user_fields.order');

    $valid = Validator::make($input, $rules);

    if ($this->can('checkout.deny')) {
      return false;
    } elseif (!Cart::has() or Cart::isTooSmall()) {
      return E_INPUT;
    } elseif ($valid->fails()) {
      return $valid;
    } else {
      \DB::transaction(function () use (&$input, &$user, &$order) {
        $user = User::findOrCreate($input);
        $order = Order::createBy(head(arrize($user)), $input);

        $goods = S(Cart::all(), function ($qty, $product) use ($order) {
          return compact('qty', 'product') + array('order' => $order->id);
        });

        OrderProduct::insert($goods);
      });

      $newUser = is_array($user);
      if ($newUser) { list($user, $password) = $user; }
      $options = compact('user', 'order') + array('block' => $this);

      if ($newUser) {
        $options += compact('password');
        $event = 'checkout.reg_user';
      } else {
        $event = 'checkout.old_user';
      }

      Event::fire($event, array($user, &$options));
      Event::fire('checkout.done', array($user, &$options));

      return $order;
    }
  }
}