<?php namespace VaneMart;

class Block_Order extends BaseBlock {
  function get_index() {
    return array('rows' => Order::all());
  }

  function get_show($id) {
    $order = Order::find($id);

    if ($order->password === Input::get('code')) {
      return $order;
    } else {
      return E_DENIED;
    }
  }
}