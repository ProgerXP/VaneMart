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
  | GET order/show/ID
  |
  | Displays order info.
  |----------------------------------------------------------------------
  | * ID            - REQUIRED.
  |--------------------------------------------------------------------*/
  function get_show($id = null) {
    if ($order = Order::find($id)) {
      $this->title = array($id);
      $this->order = $order;

      if ($order->password === $this->in('code')) {
        $goods = $order->goods()->get();
        return array('order' => $order->to_array(), 'goods' => S($goods, '?.to_array'));
      } else {
        return E_DENIED;
      }
    }
  }

  /*---------------------------------------------------------------------
  | POST order/show/ID
  |
  | Updates order fields like name and address.
  |----------------------------------------------------------------------
  | * ID            - REQUIRED.
  | * back=URL      - optional; page to return to after successful update.
  | * relink=0      - optional; if set only regenerates order password
  |   (used in permalink URL). Changes no other fields.
  |--------------------------------------------------------------------*/
  function post_show($id = null) {
    if ($order = Order::find($id)) {
      if ($this->in('relink', false)) {
        if ($order->regeneratePassword()->save()) {
          return Redirect::to($order->url());
        } else {
          return E_SERVER;
        }
      }

      $statuses = join(array_keys(__('vanemart::order.status')->get()));

      $valid = Validator::make($this->in(), array(
        'status'          => 'in:'.$statuses,
      ));

      if ($valid->fails()) {
        return $valid;
      }

      $fields = array('status', 'name', 'surname', 'phone', 'address', 'notes');
      $order->fill_raw(S::trim(Input::only($fields)));

      $changed = $order->get_dirty();

      if (!$changed) {
        //return E_UNCHANGED;
        return static::back($order->url());
      }

      $logs = array();

      foreach ($changed as $field => $value) {
        $vars = array(
          'field'       => __("vanemart::field.$field")->get(),
          'old'         => trim($order->original[$field]),
          'new'         => $value,
        );

        $type = $vars['old'] === '' ? 'add' : ($value === '' ? 'delete' : 'set');
        $logs[] = __("vanemart::order.set.line.$type", $vars);
      }

      $msg = __('vanemart::order.set.post', join($logs, "\n"))->get();

      \DB::transaction(function () use ($order, $msg) {
        $post = with(new Post)->fill_raw(array(
          'type'        => 'orders',
          'object'      => $order->id,
          'author'      => \Auth::user()->id,
          'flags'       => 'field-change',
          'body'        => $msg,
          'ip'          => Request::ip(),
        ));

        if (!$post->save()) {
          throw new Error("Cannot save new system post for order {$order->id}.");
        }

        if (!$order->save()) {
          throw new Error("Cannot update fields of order {$order->id}.");
        }
      });

      return static::back($order->url())
        ->with('status', __('vanemart::order.set.status'));
    }
  }

  function get_goods($id = null) {
    if ($order = Order::find($id)) {
      $goods = OrderProduct::name('op')
        ->where('order', '=', $order->id)
        ->join(Product::$table.' AS p', 'p.id', '=', 'product')
        ->get();

      return array('rows' => S($goods, '?.to_array'));
    }
  }
}