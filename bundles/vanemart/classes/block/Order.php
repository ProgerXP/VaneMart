<?php namespace VaneMart;

class Block_Order extends BaseBlock {
  function get_index() {
    return $this->briefs();
  }

  function get_briefs() {
    $orders = Order::order_by('updated_at', 'desc')->order_by('created_at', 'desc');
    $orders = $orders->get();

    $counts = OrderProduct
      ::where_in('order', prop('id', $orders))
      ->group_by('order')
      ->select(array('*', \DB::raw('COUNT(1) AS count')))
      ->get();
    $counts = S::keys($counts, '?->order');

    $rows = S($orders, function ($model) use (&$counts) {
      return $model->to_array() + array(
        'count'           => $counts[$model->id]->count,
      );
    });

    return compact('rows');
  }

  function get_show($id) {
    $order = Order::find($id);
    $this->viewData('title', __('vanemart::order.show.title', $id)->get());

    if ($order->password === Input::get('code')) {
      $goods = $order->goods()->get();
      return array('order' => $order->to_array(), 'goods' => S($goods, '?.to_array'));
    } else {
      return E_DENIED;
    }
  }
}