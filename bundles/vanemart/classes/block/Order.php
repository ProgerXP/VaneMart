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
           (($this->can('manager') or $this->can('order.edit.self'))
             and $order->isOf($this->user(false)));
  }

  /*---------------------------------------------------------------------
  | GET order/index
  |
  | Ouputs list of orders current user (buyer or manager) has access to.
  |----------------------------------------------------------------------
  | * by=USER_ID    - optional; if given filters orders by this user
  |   (for managers only, who might be able to see multiple users' orders).
  | * for=USER_ID   - optional; if given shows orders assigned to this manager.
  | * for_all=1     - optional; if set lists orders assigned to any manager,
  |   not just the current user (useful for super-managers able to see others).
  |--------------------------------------------------------------------*/
  function get_index() {
    $canFilterByManager = ($this->can('manager') and $this->can('order.list.all'));

    $orders = Order
      ::order_by('updated_at', 'desc')
      ->order_by('created_at', 'desc');

    if (!$this->can('order.list.all')) {
      $self = $this;

      $orders->where(function ($query) use ($self) {
        $query->where('user', '=', $user = $self->user()->id);
        $self->can('manager') and $query->or_where('manager', '=', $user);
      });
    }

    if ($user = (int) $this->in('by', 0) and $this->can('manager')) {
      $orders->where('user', '=', $user);
    }

    $shownForAllManagers = ($canFilterByManager and $this->in('for_all', 0));

    if ($canFilterByManager) {
      $manager = (int) $this->in('for', 0);
      !$manager and !$shownForAllManagers and $manager = $this->user()->id;
      $manager and $orders->where('manager', '=', $manager);
    }

    if ($orders = $orders->get()) {
      $counts = OrderProduct
        ::where_in('order', prop('id', $orders))
        ->group_by('order')
        ->select(array('*', \DB::raw('COUNT(1) AS count')))
        ->get();
      $counts = S::keys($counts, '?->order');

      $recentTime = time() - 3*24*3600;
      $current = static::detectCurrentOrder();
      $user = $this->user();

      $rows = S($orders, function ($model) use (&$counts, $recentTime, $current,
                                                $canFilterByManager, $user) {
        if ($canFilterByManager and $model->manager != $user->id) {
          $manager = User::find($model->manager)->to_array();
        } else {
          $manager = null;
        }

        return $model->to_array() + array(
          'count'           => $counts[$model->id]->count,
          'recent'          => $model->updated_at >= $recentTime,
          'current'         => $current and $model->id === $current->id,
          'forManager'      => $manager,
        );
      });
    } else {
      $rows = array();
    }

    $isManager = $this->can('manager');
    return compact('rows', 'isManager', 'canFilterByManager', 'shownForAllManagers');
  }

  /*---------------------------------------------------------------------
  | GET order/show /ID
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
        $setManagers = array();

        if ($this->editable($order)) {
          $this->layout = '.set';

          if ($this->can('order.edit.manager')) {
            $managers = User::where('perms', 'LIKE', '%manager%')->get();

            $setManagers = S::keys($managers, function ($user) {
              $name = __('vanemart::order.set.manager', $user->to_array());
              return array($user->id, $name);
            });
          }
        }

        return compact('setManagers') + $order->to_array();
      }
    }
  }

  /*---------------------------------------------------------------------
  | POST order/show /ID
  |
  | Updates order fields like name and address.
  |----------------------------------------------------------------------
  | * ID            - REQUIRED.
  | * back=URL      - optional; page to return to after successful update.
  | * relink=0      - optional; if set only regenerates order password
  |   (used in permalink URL). Changes no other fields.
  | * set_manager=0 - optional; if set only assigns new manager to the order.
  | * manager=USER_ID - required if set_manager is given.
  |--------------------------------------------------------------------*/
  function post_show($id = null) {
    if (!($order = Order::find($id))) {
      return;
    } elseif (!$this->editable($order)) {
      return false;
    } elseif ($this->in('relink', false)) {
      if ($order->regeneratePassword()->save()) {
        return Redirect::to($order->url());
      } else {
        throw new Error("Cannot save order {$order->id} with regenerated password.");
      }
    } elseif ($this->in('set_manager', false)) {
      $order->manager = $this->in('manager');

      if (!$this->can('order.edit.manager')) {
        return false;
      } elseif ($order->save()) {
        return Redirect::to($order->url());
      } else {
        throw new Error("Cannot save order {$order->id} with new manager.");
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

    $fields = array('status', 'name', 'surname', 'phone', 'city', 'address', 'notes');
    $order->fill_raw(S::trim(Input::only($fields)));

    if (!$order->dirty()) {
      return E_UNCHANGED;
    }

    $changes = $order->changeMessages();
    $msg = __('vanemart::order.set.post', join($changes, "\n"))->get();

    $post = with(new Post)->fill_raw(array(
      'type'              => 'order',
      'object'            => $order->id,
      'author'            => $this->user()->id,
      'flags'             => 'field-change '.($this->can('manager') ? 'manager' : ''),
      'body'              => $msg,
      'html'              => nl2br(HLEx::q($msg)),
      'ip'                => Request::ip(),
    ));

    \DB::transaction(function () use ($order, $post) {
      if (!$post->save()) {
        throw new Error("Cannot save new system post for order {$order->id}.");
      } elseif (!$order->save()) {
        throw new Error("Cannot update fields of order {$order->id}.");
      }
    });

    if ($order->user != $this->user()->id) {
      $to = $order->user()->first()->emailRecipient();

      \Vane\Mail::sendTo($to, 'vanemart::mail.order.post', array(
        'order'         => $order->to_array(),
        'user'          => $this->user()->to_array(),
        'post'          => $post->to_array(),
      ));
    }

    return $order;
  }

  function post_post($id = null) {
    return  \Vane\Block::execResponse('VaneMart::post@add', array('order', $id), null);
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
        return array('image' => $product->image(200)) + $product->to_array();
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