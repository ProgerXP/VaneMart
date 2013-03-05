<?php namespace VaneMart;

class BaseModel extends Eloquent {
  //= hash of class => hash of id => Model
  static $cachedModels = array();
  //= bool enables automatic url() generation
  static $hasURL = false;

  static function cached($id, $clone = true) {
    $class = get_called_class();

    if (isset(static::$cachedModels[$class]) and is_scalar($id)) {
      $cached = &static::$cachedModels[$class][$id];
      if ($cached) { return $clone ? clone $cached : $cached; }
    }
  }

  // function (BaseModel $model)
  // Caches a new model (replaces existing cache if any)
  //
  // function (false/null, int $id)
  // Caches a 'non-existing-model' state for given $id.
  //
  // function ($id, $id, ...)
  // Fetches uncached models with given IDs and caches them.
  //= array of Eloquent existing fetched models
  static function cache($model, $id = null) {
    if ($model === null or $model === false or is_object($model)) {
      !$id and $model and $id = $model->id;
      static::$cachedModels[get_called_class()][$id] = $model ? clone $model : false;
      return $model;
    } else {
      $result = array();

      foreach (static::all(func_get_args()) as $model) {
        $result[] = static::cache($model);
      }

      return $result;
    }
  }

  static function cacheAll($models) {
    if ($models instanceof \Laravel\Database\Query) {
      $models = $models->get();
    } elseif (!is_array($models) and $models) {
      $models = array($models);
    }

    foreach ($models as $model) { static::cache($model); }
    return $models;
  }

  static function all($ids = null, $columns = '*') {
    if (head((array) $columns) !== '*') {
      return parent::all($ids, $columns);
    } elseif (!$ids) {
      return static::cacheAll(parent::all());
    } else {
      $cached = $new = array();

      foreach (arrize($ids) as $id) {
        ($model = static::cached($id)) ? $cached[] = $model : ($new[] = $id);
      }

      $new and $new = static::cacheAll(parent::all($new));
      return array_merge($cached, $new);
    }
  }

  static function find($id, $columns = array('*')) {
    $query = with(new static)->query();

    if (head((array) $columns) !== '*') {
      return $query->find($id, $columns);
    } elseif (null === $cached = static::cached($id)) {
      return static::cache($query->find($id), $id);
    } else {
      return $cached;
    }
  }

  function save() {
    $result = parent::save();
    $result and static::cache($this);
    return $result;
  }

  function to_array() {
    return parent::to_array() + array(
      'url'     => $this->url(),
    );
  }

  function url() {
    if (static::$hasURL) {
      $url = $this->id;

      if (isset($this->slug) and "{$this->slug}" !== '') {
        $url .= '-'.$this->slug;
      }

      return route('vanemart::'.strtolower(class_basename($this)), $url);
    }
  }

  function get_created_at($attr = 'created_at') {
    $value = $this->get_attribute($attr);

    if (is_string($value)) {
      return strtotime($value);
    } elseif (is_object($value)) {
      return $value->getTimestamp();
    } else {
      return $value;
    }
  }

  function get_updated_at() {
    return $this->get_created_at('updated_at');
  }
}