<?php namespace VaneMart;

class File extends Eloquent {
  static $table = 'files';

  //* $path null, str - if given returns path to file with that name; can only
  //  contain a limited number of symbols including '/' - see safeName().
  //= str local path to directory where files are stored
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
    } else {
      return $name;
    }
  }

  // Generates unique base name and returns path to that non-existing file.
  //= str absolute local path
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

  // Checks if a file with that exact data is already registered in the database.
  // If so, returns its model (doesn't change its attributes like uploader, name
  // or description). If not calls place() to create a new file.
  //
  //* $file str - file data; cannot be a stream.
  //* $attributes hash - description, etc. used for new files, see place().
  //= File new saved model
  static function reuseOrPlace($file, array $attributes = array()) {
    if ($model = static::where('md5', '=', md5($file))->first()) {
      return $model;
    } else {
      return static::place($file, $attributes);
    }
  }

  // Registers new file in the database and saves it locally. Throws exceptions
  // if any error occurs.
  //* $file str file data, stream
  //* $attributes hash - file info, see the first line for the list of fields.
  //= File new saved model
  static function place($file, array $attributes = array()) {
    $attributes += array(
      'uploader'          => null,
      'desc'              => '',
      // if null is autodetected based on the extension of 'name'.
      'mime'              => null,
      'name'              => null,
      // only used to generate 'name' if 'name' is null and $file isn't a stream.
      'ext'               => null,
    );

    if (!empty($attributes['name'])) {
      $attributes['name'] = basename($attributes['name']);
    } elseif (is_resource($file)) {
      $ext = ltrim($attributes['ext'], '.');
      $attributes['name'] = substr(uniqid(), 0, 8).".$ext";

      $msg = "Placing a file from stream with random name: $attributes[name].";
      Log::info_File($msg);
    } else {
      $attributes['name'] = basename($file);
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
      $model->mime = $attributes['mime'] ?: \File::mime($model->ext, '');

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

  // Copies source stream from its current position to a local file. According to
  // comments stream_copy_to_stream() is slower than copying manually using a buffer.
  //* $path str - local path.
  //* $fsource stream - stream to save.
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

  //= str absolute local path to this file
  function file() {
    return static::storage($this->path);
  }

  // Decrements reference counter for this file and removes it if it becomes empty.
  function unused() {
    if ($this->count > 1) {
      $this->count -= 1;

      if ($this->exists and !$this->save()) {
        throw new Error("Cannot save File model of ID [{$this->id}].");
      }
    } else {
      $this->delete();
      is_file($file = $this->file()) and unlink($file);
    }

    return $this;
  }

  // Finds other files which MD5 hash is identical to this one.
  //= Query
  function same() {
    return static::where('md5', '=', $this->md5);
  }
}
File::$table = \Config::get('vanemart::general.table_prefix').File::$table;