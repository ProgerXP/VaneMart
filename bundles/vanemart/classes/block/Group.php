<?php namespace VaneMart;

class Block_Group extends ModelBlock {
  static $model = 'VaneMart\\Group';

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

      if (!Request::ajax() and $rows) {
        // precache all connected images as they're used in the view.
        File::all(prop('image', $rows));
      }

      return compact('rows');
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
}