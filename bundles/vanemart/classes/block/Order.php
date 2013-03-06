<?php namespace VaneMart;

class Block_Order extends BaseBlock {
  //= Order current model set by GET order/show
  public $order;

  //= Order
  static function detectCurrentOrder() {
    $route = \Vane\Route::current();
    if ($route and $route->lastServer instanceof self) {
      return $route->lastServer->order;
    }
  }

  /*---------------------------------------------------------------------
  | GET order/index
  |
  | Ouputs list of orders current user (buyer or manager) has access to.
  |--------------------------------------------------------------------*/
  function get_index() {
    return $this->briefs();
  }

  /*---------------------------------------------------------------------
  | GET order/briefs
  |
  | Similar to GET order/index but outputs a list with only some info.
  |--------------------------------------------------------------------*/
  function get_briefs() {
    $orders = Order::order_by('updated_at', 'desc')->order_by('created_at', 'desc');
    $orders = $orders->get();

    $counts = OrderProduct
      ::where_in('order', prop('id', $orders))
      ->group_by('order')
      ->select(array('*', \DB::raw('COUNT(1) AS count')))
      ->get();
    $counts = S::keys($counts, '?->order');

    $recentTime = time() - 3*24*3600;
    $current = static::detectCurrentOrder();

    $rows = S($orders, function ($model) use (&$counts, $recentTime, $current) {
      return $model->to_array() + array(
        'count'           => $counts[$model->id]->count,
        'recent'          => $model->updated_at >= $recentTime,
        'current'         => $current and $model->id === $current->id,
      );
    });

    return compact('rows');
  }

  /*---------------------------------------------------------------------
  | GET order/show
  |
  | Displays order info.
  |--------------------------------------------------------------------*/
  function get_show($id) {
    if ($order = Order::find($id)) {
      $this->title = array($id);
      $this->order = $order;

      if ($order->password === Input::get('code')) {
        $goods = $order->goods()->get();
        return array('order' => $order->to_array(), 'goods' => S($goods, '?.to_array'));
      } else {
        return E_DENIED;
      }
    }
  }
}