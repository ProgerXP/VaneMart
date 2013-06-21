<?php namespace VaneMart;

use Laravel\Database\Eloquent\Model as LaravelModel;

class FileListItem extends BaseModel {
  static $table = 'file_lists';

  static function relationTo(LaravelModel $model) {
    $type = lcfirst(class_basename($model));
    return static::where('type', '=', $type)->where('object', '=', $model->id);
  }

  function object() {
    return $this->belongs_to(NS.ucfirst($this->type), 'object');
  }

  function file() {
    return $this->belongs_to(NS.'File', 'file');
  }
}
FileListItem::$table = \Config::get('vanemart::general.table_prefix').FileListItem::$table;