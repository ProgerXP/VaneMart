<?php namespace VaneMart;

class Block_Cart extends BaseBlock {
  //* $skus hash 'sku' => qty, str list of SKUs to add as 1 qty
  //= hash id => Product with 'qty' attribute set
  static function fromSKU($skus) {
    if (!is_array($skus)) {
      $skus = array_filter(preg_split('/\s+/', trim($skus)));
      $skus = array_count_values($skus);
    }

    $goods = $skus ? Product::where_in('sku', array_keys($skus))->get() : array();
    $result = array();

    foreach ($goods as $model) {
      $model->qty = $skus[$model->sku];
      $result[$model->id] = $model;
    }

    return $result;
  }

  protected function init() {
    $this->filter('before', 'csrf')->only(array('add', 'set'));
  }

  protected function beforeAction($action, array $params) {
    parent::beforeAction($action, $params);
    return $this->can('cart.disable') ? false : null;
  }

  /*---------------------------------------------------------------------
  | GET cart/index
  |
  | Lists cart contents.
  |--------------------------------------------------------------------*/
  function get_index() {
    return array('rows' => $this->ajax());
  }

  function ajax_get_index() {
    return S(Cart::models(), function ($product) {
      return array('image' => $product->image(200)) + $product->to_array();
    });
  }

  /*---------------------------------------------------------------------
  | GET cart/add [/ID]
  |
  | Adds or removes (qty <= 0) cart items.
  |----------------------------------------------------------------------
  | * ID            - optional; if given and not present in ?id and ?sku
  |   adds ID product with a qty of 1.
  | * csrf=CSRF     - REQUIRED
  | * id[ID]=QTY    - optional; adds items by their ID.
  | * sku[SKU]=QTY  - optional; adds items by SKU ignoring unknown codes.
  | * checkout=1    - optional; redirects to checkout@index instead of back.
  | * clear=1       - optional; if set effect is as if doing GET cart/clear.
  |--------------------------------------------------------------------*/
  function get_add($id = null) {
    if ($this->in('clear', false)) {
      $this->input = array();
      return $this->get_clear();
    }

    $goods = static::fromSKU($this->in('sku', null));
    $goods += arrize($this->in('id', null));
    $id and $goods += array($id => 1);

    $single = null;

    foreach ($goods as $id => $item) {
      $result = Cart::put($id, is_object($item) ? $item->qty : $item);
      $result and $single = $single ? true : $result;
    }

    if ($single === true) {
      $this->status('add_many');
    } elseif ($single !== null) {
      $this->status(Cart::has($single) ? 'add_one' : 'remove', $single->to_array());
    }

    if ($this->in('checkout', null)) {
      return Redirect::to_route('vanemart::checkout');
    } else {
      return static::back();
    }
  }

  /*---------------------------------------------------------------------
  | GET cart/add_sku
  |
  | Displays form for adding items by their SKU codes. POST cart/add is
  | used to handle the action with its ?sku[] parameter.
  |--------------------------------------------------------------------*/
  function get_add_sku() {
    return true;
  }

  /*---------------------------------------------------------------------
  | GET cart/set [/ID]
  |
  | Clears cart contents and adds listed items.
  |----------------------------------------------------------------------
  | Parameters are identical to GET cart/add.
  |--------------------------------------------------------------------*/
  function get_set() {
    Cart::clear();
    return $this->makeResponse($this->get_add());
  }

  /*---------------------------------------------------------------------
  | GET cart/clear [/ID]
  |
  | Removes items from cart. If no IDs are given removes everything.
  |----------------------------------------------------------------------
  | * ID            - optional; alias to ?id[]=ID.
  | * id[]=ID       - optional; items to remove from cart.
  |--------------------------------------------------------------------*/
  function get_clear($id = null) {
    $ids = (array) $this->in('id', null);
    $id and $ids[] = $id;

    if ($ids) {
      foreach ($ids as $id) { Cart::clear($id); }
    } else {
      Cart::clear();
    }

    $this->status($ids ? 'remove' : 'clear', array('title' => ''));
    return static::back( Cart::has() ? route('vanemart::cart') : '/' );
  }
}