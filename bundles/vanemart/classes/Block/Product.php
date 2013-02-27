<?php namespace VaneMart;

class Block_Product extends ModelBlock {
  static $model = 'VaneMart\\Product';

  function ajax_get_index($id = null) {
    if ($model = static::find($id)) {
      $image = $model->image(1000);
      return compact('image') + $model->withHTML()->to_array();
    }
  }
}