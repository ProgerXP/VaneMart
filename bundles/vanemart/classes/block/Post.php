<?php namespace VaneMart;

class Block_Post extends BaseBlock {
  function get_index($type = null, $object = null) {
    $rows = Post
      ::with('author')
      ->where('type', '=', $type)
      ->where('object', '=', $object)
      ->order_by('created_at', 'desc')
      ->get();

    return array('rows' => S($rows, function ($model) {
      return $model->withHTML()->to_array();
    }));
  }

  function get_add($type = null, $object = null) {
    if (!$type) {
      return E_INPUT;
    } else {
      $hidden = array( 'back' => '', 'parent' => 0);
      $hidden = Input::only($hidden) + $hidden;
      return compact('type', 'object', 'hidden');
    }
  }

  function post_add($type = null, $object = null) {
    $this->ajax(func_get_args());
    return static::back();
  }

  function ajax_post_add($type = null, $object = null) {
    $valid = Validator::make($this->in(), array(
      'parent'            => 'int',
      'title'             => 'max:50',
      'body'              => 'required',
    ));

    if (!$type) {
      return E_INPUT;
    } elseif ($valid->fails()) {
      return $valid;
    } else {
      $model = with(new Post)
        ->fill_raw(Input::only(array('title', 'body')))
        ->fill_raw(compact('type') + array(
          'object'        => $object ?: 0,
          'parent'        => $this->in('parent', 0) ?: null,
          'flags'         => '',
          'author'        => $this->user()->id,
          'ip'            => Request::ip(),
        ));

      if ($model->save()) {
        $object and $model->object()->update(array('updated_at' => new \DateTime));
        return $model;
      } else {
        return E_SERVER;
      }
    }
  }
}