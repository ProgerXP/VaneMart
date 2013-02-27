<?php namespace VaneMart;

class Block_Cart extends BaseBlock {
  protected function init() {
    $this->filter('before', 'csrf')->on(array('post', 'put'));
  }

  function get_index() {
    return array('rows' => Cart::all());
  }

  function post_index() {
    $single = null;

    foreach ((array) Input::must('qty') as $id => $qty) {
      $result = Cart::put($id, $qty);
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

  function put_index() {
    Cart::clear();
    return $this->makeResponse($this->post_index());
  }

  function delete_index($id = null) {
    Cart::clear($id);
    $status = __('vanemart::cart.'.($id ? 'remove' : 'clear'), array('title' => ''));
    return static::back()->with('status', $status->get());
  }
}