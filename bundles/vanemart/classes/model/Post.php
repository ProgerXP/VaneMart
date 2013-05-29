<?php namespace VaneMart;

class Post extends BaseModel {
  static $table = 'posts';

  static function format($text) {
    return (string) Event::until('format.post', array(&$text));
  }

  function object() {
    return $this->belongs_to(NS.ucfirst($this->type), 'object');
  }

  function author() {
    return $this->belongs_to(NS.'User', 'author');
  }

  function parent() {
    return $this->belongs_to(__CLASS__, 'parent');
  }

  function files() {
    return FileListItem::relationTo($this);
  }

  // Ensures 'html' field is filled with formatted 'body' (file description).
  // If it isn't formats it and saves.
  function withHTML() {
    if (!$this->html) {
      $this->html = static::format($this->body);
      $this->save();
    }

    return $this;
  }

  // Ignores files that have been uploaded with error (any but 0 = UPLOAD_ERR_OK).
  //* $max int - if < 0 no limit is imposed.
  //
  //= array of File that were attached
  function attach($inputName, $max, $uploader = null) {
    $models = $list = array();
    $files = Input::file($inputName);

    if ($files) {
      // Converting single file upload into multiple which has this form:
      // array(
      //   'name' => array('up-1.txt', 'f-2.htm'),
      //   'type' => array('plain/text', 'text/html'),
      //   'tmp_name' => array(...),
      //   'error' => array(...),
      //   'size' => array(...)
      // )
      $files = S($files, '(array) ?');

      foreach ($files['error'] as $i => $error) {
        if ($max >= 0 and count($models) >= $max) {
          $having = count(array_omit($files['error']));
          $s = count($having) == 1 ? '' : 's';

          Log::info_Post("Attempting to attach $having file$s, max allowed number".
                        " is $max - ignoring the rest.");
          break;
        }

        if (!$error and is_uploaded_file($tmp = $files['tmp_name'][$i])) {
          $model = File::reuseOrPlace(file_get_contents($tmp), array(
            'uploader'    => $uploader ? $uploader->id : null,
            'mime'        => $files['type'][$i],
            'name'        => $files['name'][$i],
          ));

          // the same file might be accidentally uploaded twice - ignore.
          if (empty($models[$model->md5])) {
            $models[$model->md5] = $model;

            $list[$model->id] = array(
              'type'          => 'post',
              'file'          => $model->id,
              'object'        => $this->id,
            );
          }
        }
      }
    }

    try {
      $list and FileListItem::insert($list);
    } catch (\Exception $e) {
      foreach ($models as $model) {
        try {
          $model->unused();
        } catch (\Exception $e2) { }
      }

      throw $e;
    }

    foreach ($models as $file) {
      Event::fire('post.attached', array($this, $file));
    }

    return $models;
  }
}
Post::$table = \Config::get('vanemart::general.table_prefix').Post::$table;