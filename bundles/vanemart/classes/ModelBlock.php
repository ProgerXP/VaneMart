<?php namespace VaneMart;

class ModelBlock extends BaseBlock {
  static $model;

  static function idFrom($id) {
    return (int) ($id ?: reset(\Request::$route->parameters));
  }

  //= null, Group
  static function find($id = null) {
    if ($id instanceof static::$model) {
      return $id;
    } elseif ($id = static::idFrom($id)) {
      $class = static::$model;
      return $class::find($id);
    }
  }

  function get_index($id = null) {
    if ($model = static::find($id)) {
      if (\URI::full() !== $model->url()) {
        return Redirect::to($model->url(), 301);
      } else {
        return $this->ajax($model);
      }
    }
  }
}