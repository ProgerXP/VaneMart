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
    return Event::until('order.accessible', array($order, $this)) !== false;
  }

  function editable(Order $order) {
    return Event::until('order.editable', array($order, $this)) !== false;
  }

  function arrayInput($var, $default = '') {
    $value = array_map('strval', (array) $this->in($var, $default));
    return count($value) == 1 ? reset($value) : $value;
  }

  /*---------------------------------------------------------------------
  | GET order/index
  |
  | Ouputs list of orders current user (buyer or manager) has access to.
  |--------------------------------------------------------------------*/
  function get_index() {
    $vars = array(
      'isManager'         => $this->can('manager'),
      'can'               => array(),
      'user'              => $this->user(false),
    );

    // archive old orders
    if ($vars['isManager']) {
      $currentTime = time();
      $sKey = 'orders_archived_time';
      $days = \Vane\Current::config('general.order_archive_days');

      $lastTime = \Session::get($sKey);
      if (date('d.m.Y', $lastTime) !== date('d.m.Y', $currentTime)) {
        $date = strtotime('-'.$days.' days', strtotime(date('Y-m-d', $currentTime)));
        $date = date('Y-m-d H:i:s', $date);
        Order::where('updated_at', '<', $date)
          ->update(array( 'status' => 'archive' ));
        \Session::put($sKey, time());
      }
    }

    $query = Order::with('manager')->name('o');
    Event::fire('order.list.query', array($query, $this, &$vars['can']));

    $rows = S::keys($query->get(), '?->id');

    if ($rows) {
      foreach ($rows as &$order) {
        $order->current = false;
      }

      if ($current = static::detectCurrentOrder() and isset($rows[$current->id])) {
        $rows[$current->id]->current = true;
      }

      Event::fire('order.list.populate', array(&$rows, $this, &$vars));
    }

    $vars['rows'] = func('to_array', $rows);
    Event::fire('order.list.vars', array(&$vars, $this));

    return $vars;
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
      return $this->back($order->url());
    } elseif ($result === E_UNCHANGED) {
      return $this->back($order->url());
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
    $fields = userFields($fields, 'order');
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
      'html'              => format('post', $msg),
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
      $to = $order->user()->first();

      \Vane\Mail::sendTo($to->emailRecipient(), 'vanemart::mail.order.post', array(
        'order'         => $order->to_array(),
        'user'          => $this->user()->to_array(),
        'recipient'     => $to->to_array(),
        'post'          => $post->to_array(),
        'files'         => array(),
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
  |----------------------------------------------------------------------
  | * grouped=1     - optional; if set product groups will be displayed.
  |--------------------------------------------------------------------*/
  function get_goods($id = null) {
    if ($result = $this->ajax($id)) {
      $this->layout = 'vanemart::block.cart.goods';

      $goods = $result->order_by('group')->get();
      // cache connected images all at once.
      File::all(prop('image', $goods));

      if ($this->in('grouped', 1)) {
        $groups = Group
          ::where_in('id', prop('group', $goods))
          ->lists('title', 'id');
      } else {
        $groups = array();
      }

      $rows = S($goods, function ($product) use ($groups) {
        return array(
          'image'         => $product->image(200),
          'group'         => array_get($groups, $product->group),
        ) + $product->to_array();
      });

      return compact('rows') + array('showGroups' => true);
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