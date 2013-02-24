<?php namespace VaneMart;

class Block_Group extends BaseBlock {
  static function idFrom($id) {
    return (int) ($id ?: reset(\Request::$route->parameters));
  }

  //= null, Group
  static function find($id = null) {
    if ($id instanceof Group) {
      return $id;
    } else {
      return ($id = static::idFrom($id)) ? Group::find($id) : null;
    }
  }

  function get_title($id = null) {
    if ($group = static::find($id)) {
      return $group->title;
    }
  }

  function get_index($id = null) {
    if ($group = static::find($id)) {
      if (\URI::full() !== $group->url()) {
        return Redirect::to($group->url(), 301);
      } else {
        return $this->ajax($group);
      }
    }
  }

  function ajax_get_index($id = null) {
    if ($group = static::find($id)) {
      $query = $group->goods(true)
        ->where_null('variation')
        ->where('available', '=', 1)
        ->order_by('sort');

      return array('rows' => $query->get());
    }
  }
}