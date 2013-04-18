<?php namespace VaneMart;

class Block_Checkout extends BaseBlock {
  static function orderInfo($block, Order $order) {
    $response = \Vane\Block::execCustom($block, array(
      'args'              => $order->id,
      'input'             => array('code' => $order->password),
      'prepare'           => function ($block) { $block->user = false; },
      'response'          => true,
      'return'            => 'response',
    ));

    return $response->render();
  }

  protected function init() {
    $this->filter('before', 'csrf')->on('post');
  }

  function prereq() {
    if (!Cart::has()) {
      return $this->back();
    } elseif ($this->can('checkout.deny')) {
      return false;
    } elseif ($min = Cart::isTooSmall()) {
      $this->status('small', array(
        'min'     => Str::langNum('general.price', $min),
        'total'   => Str::langNum('general.price', Cart::subtotal()),
      ));

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
    $valid = Validator::make($input, array(
      'email'           => 'required|email',
      'name'            => 'required',
      'surname'         => 'required',
      'city'            => 'required|min:2',
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
      $recipient = head(arrize($user))->emailRecipient();

      if (is_array($user)) {
        list($user, $password) = $user;

        \Vane\Mail::sendTo($recipient, 'vanemart::mail.user.reg_on_order',
                           compact('password') + $user->to_array());
      }

      $order = null;

      \DB::transaction(function () use (&$input, $user, &$order) {
        $order = Order::createBy($user, $input);

        $goods = S(Cart::all(), function ($qty, $product) use ($order) {
          return compact('qty', 'product') + array('order' => $order->id);
        });

        OrderProduct::insert($goods);
      });

      \Vane\Mail::sendTo($recipient, 'vanemart::mail.checkout.user', array(
        'user'        => $user->to_array(),
        'order'       => $order->to_array(),
        'orderHTML'   => static::orderInfo('VaneMart::order@show', $order),
        'goodsHTML'   => static::orderInfo('VaneMart::order@goods', $order),
      ));

      Cart::clear();
      return $order;
    }
  }
}