<?php namespace VaneMart;

class File extends BaseModel {
  static $table = 'files';
  static $hasURL = true;

  static function safeName($name) {
    if (strpos($name, '..') !== false) {
      throw new Error("Unsafe File name [$name].");
    } else {
      return $name;
    }
  }

  //* $path null, str - if given returns path to file with that name; can only
  //  contain a limited number of symbols including '/' - see safeName().
  //= str local path to directory where files are stored
  static function storage($path = null) {
    $path and $path = static::safeName($path);
    return Event::until('file.path', "$path");
  }

  // Generates unique base name and returns path to that non-existing file.
  //= str absolute local path
  static function generatePath($name) {
    return Event::result('file.new.path', $name, function ($path) {
      return file_exists($path) ? 'a non-unique file path' : true;
    });
  }

  static function generateID() {
    return (int) Event::result('file.new.id', function ($id) {
      return (is_numeric($id) and $id) ? true : 'a non-integer or zero';
    });
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
      return $model->used();
    } else {
      return static::place($file, $attributes);
    }
  }

  // Registers new file in the database and saves it locally. Throws exceptions
  // if any error occurs.
  //* $file str file data, stream - this is not a path!
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
    } else {
      $ext = strtolower(ltrim($attributes['ext'], '.'));
      $attributes['name'] = substr(uniqid(), 0, 8).".$ext";

      $msg = "Placing a file without explicit name, generated random: $attributes[name].";
      Log::info_File($msg);
    }

    $ext = $attributes['ext'] = strtolower(ltrim(S::ext($attributes['name']), '.'));
    $ext === '' and $attributes['ext'] = 'dat';

    $dest = static::generatePath($attributes['name']);
    S::mkdirOf($dest);

    $attributes['path'] = S::tryUnprefix($dest, static::storage());

    if (is_resource($file)) {
      $attributes['size'] = static::streamCopyTo($dest, $file);
    } else {
      $attributes['size'] = strlen($file);

      if (!file_put_contents($dest, $file, LOCK_EX)) {
        throw new Error("Cannot write new File data [$dest].");
      }
    }

    try {
      // explicit ID so it's harder to guess new file's ID (e.g. to access it directly
      // from web) since they're not sequental.
      $attributes['id'] = static::generateID();

      $model = with(new static)->fill_raw($attributes);
      $model->md5 = md5_file($dest);
      $model->mime = $attributes['mime'] ?: \File::mime($model->ext, '');

      return Event::insertModel($model, 'file');
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

  static function fromLists($type, array $objects) {
    $fileRows = FileListItem
      ::where('type', '=', $type)
      ->where_in('object', $objects)
      ->join(static::$table.' AS f', 'f.id', '=', 'file')
      ->get();

    $files = array();

    foreach ($fileRows as $file) {
      $files[$file->object][] = with(new static)
        ->fill_raw($file->attributes)->to_array();
    }

    return $files;
  }

  function uploader() {
    return $this->has_many(NS.'User', 'uploader');
  }

  //= str absolute local path to this file
  function file() {
    return static::storage($this->path);
  }

  function used() {
    if (Event::until('file.used', $this)) {
      return $this;
    } else {
      throw new Error("Cannot update usage counter of File [{$model->id}].");
    }
  }

  // Decrements reference counter for this file and removes it if it becomes empty.
  function unused() {
    if ($this->uses > 1) {
      $this->uses -= 1;

      if ($this->exists and !$this->save()) {
        throw new Error("Cannot save File model of ID [{$this->id}].");
      }
    } else {
      $this->delete();
      is_file($file = $this->file()) and unlink($file);
      Event::fire('file.deleted', $this);
    }

    return $this;
  }

  // Finds other files which MD5 hash is identical to this one.
  //= Query
  function same() {
    return static::where('md5', '=', $this->md5);
  }

  function url() {
    if ($url = parent::url()) {
      $this->ext and $url .= '.'.$this->ext;
    }

    return $url;
  }
}
File::$table = \Config::get('vanemart::general.table_prefix').File::$table;