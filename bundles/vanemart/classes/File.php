<?php namespace VaneMart;

class File extends Eloquent {
  static $table = 'files';

  static function storage($name = null) {
    return \Bundle::path('vanemart').'storage/files/'.static::safeName($name);
  }

  static function safeName($name) {
    if (strpos($name, '..') !== false) {
      throw new Error("Unsafe File name [$name].");
    } else {
      return ltrim($name, '\\/');
    }
  }

  //= File new saved model
  static function place($file, array $attributes = array()) {
    $attributes += array('uploader' => null, 'desc' => '', 'ext' => 'dat');
    $attributes['ext'] = ltrim($attributes['ext'], '.');

    if (is_resource($file)) and !$attributes['name']) {
      $attributes['name'] = substr(uniqid(), 0, 8).'.'.$attributes['ext']);
      Log::info_File('Placing a file from stream with randomly generated name.');
    }

    $dest = static::storage($attributes['name']);
    S::mkdirOf($dest);

    if (is_resource($file)) {
      $attributes['size'] = static::streamCopyTo($dest, $file);
    } else {
      $attributes['name'] = basename($file);
      $attributes['ext'] = ltrim(S::ext($file), '.');
      $attributes['size'] = filesize($file);

      if (!copy($file, $dest)) {
        throw new Error("Cannot copy File [$file] to [$dest].");
      }
    }

    try {
      $attributes['mime'] = \File::mime($attributes['ext'], '');
      $model = with(new static)::fill_raw($attributes);

      if (!$model->save()) {
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

  function path() {
    return static::storage($this->name);
  }
}
File::$table = \Config::get('vanemart::general.table_prefix').File::$table;