<?php namespace VaneMart;

class Block_Group extends ModelBlock {
  static $model = 'VaneMart\\Group';

  static function listResponse(array $rows) {
    // precache all connected images as they're used in the view.
    File::all(prop('image', $rows));

    return array('rows' => S($rows, function ($product) {
      return array('image' => $product->image(300)) + $product->to_array();
    }));
  }

  function get_title($id = null) {
    if ($group = static::find($id)) {
      return $group->title;
    }
  }

  function ajax_get_index($id = null) {
    if ($group = static::find($id)) {
      $rows = $group->goods(true)
        ->where_null('variation')
        ->where('available', '=', 1)
        ->order_by('sort')
        ->get();

      if (Request::ajax()) {
        return $rows;
      } elseif ($rows) {
        return static::listResponse($rows);
      }
    }
  }

  function get_titleByProduct($id = null) {
    return $this->actByProduct('get_title', $id);
  }

  function get_listByProduct($id = null) {
    return $this->actByProduct('ajax_get_index', $id);
  }

  protected function actByProduct($method, $id) {
    if ($id = static::idFrom($id) and $model = Product::find($id) and $model->group) {
      $view = $this->name.'.'.substr(strrchr($method, '_'), 1);
      View::exists($view) and $this->layout = View::make($view);
      return $this->$method($model->group);
    }
  }

  function get_byList($name = 'main') {
    $query = ProductListItem
      ::order_by('sort')
      ->where('type', '=', $name);

    if ($default = $this->in('default', 'main')) {
      $query->or_where('type', '=', $default);
    }

    $list = $query->get();

    if ($default and !S::first($list, array('?->type === ?', $name))) {
      $type = $default;
    } else {
      $type = $name;
    }

    $list = S::keep($list, array('?->type === ?', $type));
    $goods = S::keys(Product::all(prop('product', $list)), '?->id');
    $ordered = array();

    foreach ($list as $item) {
      $product = &$goods[$item->product];
      $product and $ordered[] = $product;
    }

    $this->layout = '.index';
    return static::listResponse($ordered);
  }
}