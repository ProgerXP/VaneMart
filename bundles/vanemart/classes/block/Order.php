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

  protected function init() {
    parent::init();
    $this->filter('before', 'vane::auth')->only('index');
    $this->filter('before', 'csrf')->on('post');
  }

  function accessible(Order $order) {
    return $this->can('order.show.all') or
           $order->isOf($this->user(false)) or
           $order->password === $this->in('code', '');
  }

  function editable(Order $order) {
    return $this->can('order.edit.all') or
           ($this->can('order.edit.self') and $order->isOf($this->user(false)));
  }

  /*---------------------------------------------------------------------
  | GET order/index
  |
  | Ouputs list of orders current user (buyer or manager) has access to.
  |--------------------------------------------------------------------*/
  function get_index() {
    $orders = Order::order_by('updated_at', 'desc')->order_by('created_at', 'desc');

    if (!$this->can('order.list.all')) {
      $field = $this->can('manager') ? 'manager' : 'user';
      $orders->where($field, '=', $this->user()->id);
    }

    $orders = $orders->get();
    if (!$orders) { return; }

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

      if (!$this->accessible($order)) {
        return false;
      } else {
        $this->editable($order) and $this->layout = '.set';
        return $order->to_array();
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
    if (!($order = Order::find($id))) {
      return;
    } elseif ($this->in('relink', false)) {
      if ($order->regeneratePassword()->save()) {
        return Redirect::to($order->url());
      } else {
        throw new Error("Cannot save order {$order->id} with regenerated password.");
      }
    }

    $result = $this->ajax($order->id);

    if ($result instanceof Order) {
      $this->status('set');
      return static::back($order->url());
    } elseif ($result === E_UNCHANGED) {
      return static::back($order->url());
    } else {
      return $result;
    }
  }

  function ajax_post_show($id = null) {
    $valid = Validator::make($this->in(), array(
      'status'          => 'in:'.join(',', Order::statuses()),
    ));

    if ($valid->fails()) {
      return $valid;
    } elseif (!($order = Order::find($id))) {
      return;
    } elseif (!$this->editable($order)) {
      return false;
    }

    $fields = array('status', 'name', 'surname', 'phone', 'address', 'notes');
    $order->fill_raw(S::trim(Input::only($fields)));

    if (!$order->dirty()) {
      return E_UNCHANGED;
    }

    $changes = $order->changeMessages();
    $msg = __('vanemart::order.set.post', join($changes, "\n"))->get();

    $post = with(new Post)->fill_raw(array(
      'type'              => 'orders',
      'object'            => $order->id,
      'author'            => $this->user()->id,
      'flags'             => 'field-change',
      'body'              => $msg,
      'ip'                => Request::ip(),
    ));

    \DB::transaction(function () use ($order, $post) {
      if (!$post->save()) {
        throw new Error("Cannot save new system post for order {$order->id}.");
      } elseif (!$order->save()) {
        throw new Error("Cannot update fields of order {$order->id}.");
      }
    });

    return $order;
  }

  /*---------------------------------------------------------------------
  | GET order/goods
  |
  | Ouputs list of products in the order.
  |--------------------------------------------------------------------*/
  function get_goods($id = null) {
    if ($result = $this->ajax($id)) {
      $this->layout = 'vanemart::block.cart.goods';

      $goods = $result->get();
      // cache connected images all at once.
      File::all(prop('image', $goods));

      $rows = S($goods, function ($product) {
        return array('image' => $product->image(150)) + $product->to_array();
      });

      return compact('rows');
    } else {
      return $result;
    }
  }

  function ajax_get_goods($id = null) {
    if (!($order = Order::find($id))) {
      return;
    } elseif (!$this->accessible($order)) {
      return false;
    } else {
      return Product::name('p')
        ->join(OrderProduct::$table, 'p.id', '=', 'product')
        ->where('order', '=', $order->id);
    }
  }
}