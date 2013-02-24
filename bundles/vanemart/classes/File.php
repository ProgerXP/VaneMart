<?php namespace VaneMart;

class File extends Eloquent {
  static $table = 'files';

  static function storage($path = null) {
    $path and $path = static::safeName($path);

    if ("$path" === '' or strpbrk($path[0], '\\/') === false) {
      return \Bundle::path('vanemart').'storage/files/'.$path;
    } else {
      return $path;
    }
  }

  static function safeName($name) {
    if (strpos($name, '..') !== false) {
      throw new Error("Unsafe File name [$name].");
    }

    return $name;
  }

  static function generatePath($name) {
    list($name, $ext) = S::chopTo('.', static::safeName($name));
    "$ext" === '' or $ext = ".$ext";

    $base = static::storage();
    $result = "$base$name[0]/$name$ext";

    if (is_file($result)) {
      $i = 1;
      do {
        $result = "$base$name[0]/$name-".++$i.$ext;
      } while (is_file($result));
    }

    return $result;
  }

  //= File new saved model
  static function reuseOrPlace($file, array $attributes = array()) {
    if ($model = static::where('md5', '=', md5($file))->first()) {
      return $model;
    } else {
      return static::place($file, $attributes);
    }
  }

  //= File new saved model
  static function place($file, array $attributes = array()) {
    $attributes += array('uploader' => null, 'desc' => '');
    isset($attributes['ext']) and $attributes['ext'] = ltrim($attributes['ext'], '.');

    if (empty($attributes['name'])) {
      if (is_resource($file)) {
        $ext = ltrim(array_get($attributes, 'ext', 'dat'), '.');
        $attributes['name'] = substr(uniqid(), 0, 8).".$ext";
        Log::info_File("Placing a file from stream with randomly generated name".
                       " $attributes[name].");
      } else {
        $attributes['name'] = basename($file);
      }
    } else {
      $attributes['name'] = basename($attributes['name']);
    }

    $ext = $attributes['ext'] = ltrim(S::ext($attributes['name']), '.');
    $ext === '' and $attributes['ext'] = 'dat';

    $dest = static::generatePath($attributes['name']);
    S::mkdirOf($dest);

    $attributes['path'] = S::tryUnprefix($dest, static::storage());

    if (is_resource($file)) {
      $attributes['size'] = static::streamCopyTo($dest, $file);
    } else {
      $attributes['size'] = filesize($file);

      if (!copy($file, $dest)) {
        throw new Error("Cannot copy File [$file] to [$dest].");
      }
    }

    try {
      $model = with(new static)->fill_raw($attributes);
      $model->md5 = md5_file($dest);
      $model->mime = \File::mime($model->ext, '');

      if ($model->save()) {
        return $model;
      } else {
        throw new Error("place() cannot insert new File row for [$attributes[name]].");
      }
    } catch (\Exception $e) {
      unlink($dest);
      throw $e;
    }
  }

  //= int number of bytes written
  static function streamCopyTo($path, $fsource) {
    $fdest = fopen($path, 'wb');
    if (!$fdest) {
      throw new Error("Cannot open File [$dest] for writing.");
    }

    $written = 0;

    while (!feof($fsource)) {
      $written += fwrite($fdest, fread($fsource, 65536));
    }

    fclose($fdest);
    return $bytes;
  }

  function file() {
    return static::storage($this->path);
  }

  function unused() {
    if ($this->count > 1) {
      $this->count -= 1;

      if ($this->exists and !$this->save()) {
        throw new Error("Cannot save File model of ID [{$this->id}].");
      }
    } else {
      is_file($file = $this->file()) and unlink($file);
      $this->delete();
    }

    return $this;
  }
}
File::$table = \Config::get('vanemart::general.table_prefix').File::$table;