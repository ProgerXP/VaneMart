<?php namespace VaneMart;

class Block_Product extends ModelBlock {
  static $model = 'VaneMart\\Product';
  public $product;      //= Product model

  /*---------------------------------------------------------------------
  | GET goods/index /ID
  |
  | Displays product info.
  |--------------------------------------------------------------------*/
  function ajax_get_index($id = null) {
    if ($model = static::find($id)) {
      $vars = $model->to_array() + array('root' => $model->group()->first()->root()->title);

      $this->title = $vars['title'];
      // setting explicit title to avoid store's name being appended to $winTitle
      // - this is better for SEO as search term match will be more exact and full.
      $this->viewData('winTitle', __('vanemart::product.index.title', $vars));

      $this->product = $model;
      $image = $model->image(1000);
      return compact('image') + $model->withHTML()->to_array();
    }
  }
}