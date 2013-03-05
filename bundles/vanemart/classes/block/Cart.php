<?php namespace VaneMart;

class Block_Cart extends BaseBlock {
  //* $skus hash 'sku' => qty, str list of SKUs to add as 1 qty
  //= hash id => Product with 'qty' attribute set
  static function fromSKU($skus) {
    $result = array();

    is_array($skus) or $skus = S::combine(explode(' ', $skus), 1);
    $skus = S::keys($skus, '#trim#');
    $goods = Product::where_in('sku', array_keys($skus))->get();

    foreach ($goods as $model) {
      $result[$model->id] = $model->fill_raw(array('qty' => $skus[$model->sku]));
    }

    return $result;
  }

  protected function init() {
    $this->filter('before', 'csrf')->only(array('add', 'set'));
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
  |--------------------------------------------------------------------*/
  function get_add($id = null) {
    $goods = static::fromSKU(Input::get('sku')) + arrize(Input::get('id'));
    $id and $goods += array($id => 1);
    $single = null;

    foreach ($goods as $id => $item) {
      $result = Cart::put($id, is_object($item) ? $item->qty : $item);
      $result and $single = $single ? null : $result;
    }

    if ($single) {
      $key = 'vanemart::cart.'.($single[1] ? 'put' : 'removed');
      \Session::flash('status', HLEx::lang($key, $single[0]->to_array()));
    }

    if (Input::get('checkout')) {
      return Redirect::to_route('vanemart::checkout');
    } else {
      return static::back();
    }
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
    $ids = (array) Input::get('id');
    $id and $ids[] = $id;

    if ($ids) {
      foreach ($ids as $id) { Cart::clear($id); }
      $status = 'vanemart::cart.remove';
    } else {
      Cart::clear();
      $status = 'vanemart::cart.clear';
    }

    return static::back( Cart::has() ? route('vanemart::cart') : '/' )
      ->with('status', __($status, array('title' => ''))->get());
  }
}