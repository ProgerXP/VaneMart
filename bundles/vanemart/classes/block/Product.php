<?php namespace VaneMart;

class Block_Product extends ModelBlock {
  static $model = 'VaneMart\\Product';
  public $product;      //= Product model

  function ajax_get_index($id = null) {
    if ($model = static::find($id)) {
      $this->product = $model;
      $image = $model->image(1000);
      return compact('image') + $model->withHTML()->to_array();
    }
  }
}