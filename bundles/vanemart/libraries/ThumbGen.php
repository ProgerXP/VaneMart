<?php
/*
  Thumbnail generator class using GD extension
  by Proger_XP      | http://proger.i-forge.net
  in public domain  | http://github.com/ProgerXP/ThumbGen
*/

//? ThumbGen::make('full.png')->size(100, 200)->watermark('logo.gif')->scaled()
//      //=> %TEMP%/thumbgen/97/1df7de24a0f12a0c4a2c0010b954f6.jpg
class ThumbGen {
  //= int size in bytes of buffer used when copying stream to stream
  static $buffer = 8192;
  //= int permission mask for directories created by ThumbGen
  static $tempDirPerms = 0775;

  //= str file name of source (full-size) image
  protected $source;
  //= str e.g. 'jpg', null detect from extension
  protected $sourceType;
  //= str path where scaled thumbnails are stored
  protected $tempPath;
  //= str local path to fetched remote file, null if it wasn't downloaded yet
  protected $tempRemote;
  //= str format of generated thumbnail
  protected $type = 'jpg';
  //= int 0-100 (inclusive) quality/compression strength of the generated thumbnail
  protected $quality = 75;
  //= int seconds to keep downloaded remote images
  protected $remoteCacheTTL = 86400;
  //= array watermark configuration, see watermarkOptions()
  protected $watermark;
  //= str forced lcation of the scaled thumb file, null autodetect
  protected $cacheFile;

  //= int dimension in pixels of scaled thumb image
  protected $width = 0, $height = 0;
  //= int constrant for thumb width
  protected $widthMin = 1, $heightMin = 1;
  //= int constrant for thumb height
  protected $widthMax = 5000, $heightMax = 5000;
  //= int step in pixels to granulate thumb width/height
  protected $step = 1;
  //= bool when step is used round dimensions up or down
  protected $stepUp = false;
  //= bool if thumb's largest dimension can exceed its value or not
  protected $contain = true;

  static function wrongArg($func, $msg) {
    throw new InvalidArgumentException(get_called_class()."->$func: $msg");
  }

  static function mkdirOf($file) {
    is_dir($dir = dirname($file)) or mkdir($dir, static::$tempDirPerms, true);

    if (!is_dir($dir)) {
      throw new RuntimeException(get_called_class()." cannot create directory [$dir].");
    }
  }

  // Calculates new image dimensions according to given restrictions.
  //* $file str - path to image recognized by GD.
  //* $maxWidth int
  //* $maxHeight int
  //* $contain bool - if true returned sizes won't exceel $maxXXX, otherwise
  //  the smallest will be set to $maxXXX and another will scale up accordingly.
  //= array ($newWidth, $newHeight)
  //
  //? calcDimensionsFor('pic.jpg', 100, 200, true)      //=> array(100, 173)
  //? calcDimensionsFor('pic.jpg', 100, 200, false)     //=> array(119, 200)
  static function calcDimensionsFor($file, $maxWidth, $maxHeight, $contain = true) {
    list($width, $height) = getimagesize($file);
    $func = $contain ? 'min' : 'max';
    $ratio = $func($maxWidth / $width, $maxHeight / $height);
    return array($width * $ratio, $height * $ratio);
  }

  // Resizes the image using given settings.
  static function scaleImage($file, $maxWidth, $maxHeight, array $options) {
    $options += array(
      'saveTo'            => null,
      'inFormat'          => substr(strrchr($file, '.'), 1),
      'outFormat'         => null,
      'outQuality'        => 75,
      'returnThumb'       => false,
      'watermarks'        => array(),
    );
    $options['inFormat'] === 'jpg' and $options['inFormat'] = 'jpeg';

    list($thumbWidth, $thumbHeight) =
      static::calcDimensionsFor($file, $maxWidth, $maxHeight);
    $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);

    if (!$thumb) {
      static::wrongArg(__FUNCTION__, 'imagecreatetruecolor() has failed.');
    }

  // imagealphablending() messes up blending of semi-transparent PNG onto a JPEG.
  //imagealphablending($thumb, false);
  imagesavealpha($thumb, true);

    $create = 'imagecreatefrom'.$options['inFormat'];
    $original = function_exists($create) ? $create($file) : null;

    if (!$original) {
      static::wrongArg(__FUNCTION__, "$create() has failed.");
    }

    $width = imagesx($original);
    $height = imagesy($original);

      if (!imagecopyresampled($thumb, $original, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height)) {
        throw new RuntimeException(get_called_class().': imagecopyresampled() has failed.');
      }

      imagedestroy($original);

    if ($marks = $options['watermarks']) {
      $ratioX = $thumbWidth / $width;
      $ratioY = $thumbHeight / $height;

      foreach ((array) $marks as $mark) {
        is_array($mark) or $mark = array('file' => $mark);
        static::watermarkOver($thumb, compact('ratioX', 'ratioY') + $mark);
      }
    }

    $output = $options[ isset($options['outFormat']) ? 'outFormat' : 'inFormat' ];
    $output === 'jpg' and $output = 'jpeg';
    $output = "image$output";

    if (!function_exists($output) or !$output($thumb, $options['saveTo'], $options['outQuality'])) {
      throw new RuntimeException(get_called_class().": $output has failed.");
    }

    if (empty($options['returnThumb'])) {
      imagedestroy($thumb);
    } else {
      return $thumb;
    }
  }

  // Overlays a watermark over $image (GD handle).
  static function watermarkOver($image, $options) {
    is_array($options) or $options = array('file' => $options);

    $options += array(
      'file'              => null,
      'format'            => substr(strrchr($options['file'], '.'), 1),
      'x'                 => 0.5,
      'y'                 => 0.5,
      'ratioX'            => 1,
      'ratioY'            => 1,
    );

    $create = 'imagecreatefrom'.$options['format'];
    $mark = function_exists($create) ? $create($options['file']) : null;

    if (!$mark) {
      static::wrongArg(__FUNCTION__, "$create has failed.");
    }

    $width = imagesx($mark);
    $height = imagesy($mark);
    $newWidth = $width * $options['ratioX'];
    $newHeight = $height * $options['ratioY'];

    $x = round(imagesx($image) - $newWidth) * $options['x'];
    $y = round(imagesy($image) - $newHeight) * $options['y'];

    if (!imagecopyresampled($image, $mark, $x, $y, 0, 0, $newWidth, $newHeight,
                            $width, $height)) {
      throw new RuntimeException(get_called_class().': imagecopyresampled() has failed.');
    }

    imagedestroy($mark);
  }

  static function make($source, $width = null, $height = null) {
    return new static($source, $width, $height);
  }

  //* $source str - path to full size image.
  //* $width int
  //* $height int
  function __construct($source, $width = null, $height = null) {
    $this->source = "$source";
    $this->size($width, $height);
    $this->temp(rtrim(sys_get_temp_dir(), '\\/').'/thumbgen');
  }

  function __destruct() {
    $this->tempRemote and @unlink($this->tempRemote);
  }

  /*-----------------------------------------------------------------------
  | ACCESSORS
  |----------------------------------------------------------------------*/

  function temp($path = null) {
    if (!$path) {
      return $this->tempPath;
    } else {
      $this->tempPath = rtrim($path, '\\/').'/';

      if (strlen($this->tempPath) < 3) {
        static::wrongArg(__FUNCTION__, "new path [$path] is too short.");
      } else {
        return $this;
      }
    }
  }

  function size($width = null, $height = null) {
    if (func_num_args()) {
      $this->width = max(0, $width);
      $this->height = max(0, $height);
      return $this;
    } else {
      return array($this->width, $this->height);
    }
  }

  // Sets min and max width or height value, in pixels. Has effect only if using
  // scaled() or doing normalize() before generate().
  //* $dimension str - 'width' or 'height'.
  //* $min int
  //* $max int
  function restrict($dimension, $min, $max = 5000) {
    $dimension = strtolower($dimension);

    if ($dimension !== 'width' and $dimension !== 'height') {
      static::wrongArg(__FUNCTION__, "wrong \$dimension [$dimension].");
    } else {
      $this->{"{$dimension}Min"} = max(1, $min);
      $this->{"{$dimension}Max"} = max(1, $max);
      return $this;
    }
  }

  // Specifies how much scaled width and height may vary. Useful if exposing thumb
  // generation to the user so he can't abuse generating them by changing dimensions
  // by one pixel. Also useful to make more effective and granular cache.
  // Has effect only if using scaled() or doing normalize() before generate().
  function step($step, $roundUp = false) {
    $this->step = max(1, $step);
    $this->stepUp = (bool) $roundUp;
    return $this;
  }

  function remoteCacheTTL($sec) {
    $this->remoteCacheTTL = max(0, $sec);
    return $this;
  }

  function contain($enable = true) {
    $this->contain = (bool) $enable;
    return $this;
  }

  function fill($enable = true) {
    return $this->contain(!$enable);
  }

  // Sets generated thumbnail format and optionally quality/compression strength.
  function type($type, $quality = null) {
    $type = strtolower($type);
    $this->type = $type === 'jpeg' ? 'jpg' : $type;
    $quality and $this->quality = min(max($quality, 0), 100);
    return $this;
  }

  function sourceType($type) {
    $this->sourceType = $type;
    return $this;
  }

  //= bool indicates if source image resides on the local system
  function isLocal() {
    return strpos(substr($this->source, 0, 10), '://') === false;
  }

  // function ($path)
  // Forces scaled() thumb be placed in $path.
  //
  // function (null)
  // Makes ThumbGen determine scaled() thumb location based on source file name
  // and temp() directory.
  //
  // function ()
  // Returns path where scale() will place the new file. It might not exist yet.
  //= str
  function cacheFile($set = null) {
    if (func_num_args()) {
      $this->cacheFile = $set;
      return $this;
    } elseif ($this->cacheFile) {
      return $this->cacheFile;
    } else {
      $md5 = md5($this->source);
      return $this->tempPath.substr($md5, 0, 2).'/'.substr($md5, 2).
             "-{$this->width}-{$this->height}.{$this->ext()}";
    }
  }

  //= str file extension of scaled thumb
  function ext() {
    return $this->type;
  }

  // Assigns a watermark that's put over the scaled thumbnail. Multiple instances
  // ($count) of this watermark are evenly distributed across Y axis.
  //* $count int - times to overlay the same watermark.
  //* $file str - path to watermark image in any format recognized by GD.
  //* $x float - location on X axis of the watermark relative to image width (0...1).
  function watermarks($count, $file, $x = 0.5) {
    // $y is set to mean distribution for multiple watermarks.
    $this->watermark($file, 0.5, $x);
    $this->watermark['count'] = $count;
    return $this;
  }

  // Assigns a watermark that's put over the scaled thumbnail.
  //* $file str - path to watermark image in any format recognized by GD.
  //* $y float - location on Y axis of the watermark relative to image height (0...1).
  //* $x float - location on X axis of the watermark relative to image width (0...1).
  function watermark($file, $y = 0.5, $x = 0.5) {
    @list($file, $format) = (array) $file;
    if (!$format) { unset($format); }
    $this->watermark = compact('file', 'x', 'y');
    return $this;
  }

  /*-----------------------------------------------------------------------
  | ACTIONS
  |----------------------------------------------------------------------*/

  // Scales source image and caches it unless cache exists and is upToDate().
  //= str path to scaled file (see cacheFile())
  function scaled() {
    if (!$this->upToDate()) {
      $this->keepAspect();
      $this->normalize();
      $this->upToDate() or $this->generate();
    }

    return $this->cacheFile();
  }

  // Determines if cache for source image exists and is up to date with it.
  //* $cacheFile str, null use cacheFile() - path to presumably cached thumb.
  function upToDate($cacheFile = null) {
    $cacheFile or $cacheFile = $this->cacheFile();

    if (is_file($cacheFile) and $cacheTime = filemtime($cacheFile)) {
      $this->isLocal() and $time = filemtime($this->source);

      if (empty($time)) {
        if ($this->remoteCacheTTL) {
          $time = time() - $this->remoteCacheTTL;
        } else {
          return true;
        }
      }

      return $cacheTime >= $time;
    }
  }

  // Calculates and assigns new thumbnail dimensions using previously assigned
  // width(), height() and contain() options.
  function keepAspect() {
    list($width, $height) =
      static::calcDimensionsFor($this->local(), $this->width ?: 9999,
                                $this->height ?: 9999, $this->contain);

    return $this->size($width, $height);
  }

  // Applies constrains set with restrict() including min/max dimensions and step().
  function normalize() {
    $rounding = $this->stepUp ? 'ceil' : 'floor';
    $this->normalizeDimension('width', $rounding);
    $this->normalizeDimension('height', $rounding);

    return $this;
  }

  protected function normalizeDimension($dim, $rounding) {
    $this->$dim = min(max($this->$dim, $this->{"{$dim}Min"}), $this->{"{$dim}Max"});
    $this->step > 1 and $this->$dim = $this->step * $rounding($this->$dim / $this->step);
  }

  // Returns path to source image in the local FS. Downloads it if necessary.
  //= str full path
  function local() {
    $file = $this->source;

    if ($this->isLocal()) {
      if (is_file($file)) {
        return $file;
      } else {
        $class = get_class($this);
        throw new RuntimeException("$class: non-existent source file [$file].");
      }
    } elseif ($this->tempRemote) {
      return $this->tempRemote;
    } else {
      do {
        $temp = $this->tempPath.mt_rand().strrchr($file, '.');
      } while (is_file($temp));

      return $this->tempRemote = $this->fetchTo($temp);
    }
  }

  protected function fetchTo($temp) {
    static::mkdirOf($temp);

    $to = fopen($temp, 'wb');
    if (!$to) {
      throw new RuntimeException(get_class($this).": cannot create temp file [$temp].");
    }

    $from = fopen($this->source, 'rb');
    if (!$from) {
      throw new RuntimeException(get_class($this).": cannot read remote file [{$this->source}].");
    }

    while (!feof($from)) {
      fwrite($to, fread($from, static::$buffer));
    }

    fclose($from);
    fclose($to);
    return $temp;
  }

  // Generates and saves new thumbnail. Overlays watermarks if assigned.
  // Doesn't do normalizations, use cache or calculate aspect dimensions - uses
  // currently set width() and height().
  function generate() {
    $options = array(
      'saveTo'            => $this->cacheFile(),
      'outFormat'         => $this->type,
      'outQuality'        => $this->gdQuality(),
      'watermarks'        => $this->watermarkOptions(),
    );

    $type = $this->sourceType and $options['inFormat'] = $type;

    static::mkdirOf($this->cacheFile());
    static::scaleImage($this->local(), $this->width, $this->height, $options);
    return $this;
  }

  //= int suitable for imageXXX() function corresponding to thumb format
  function gdQuality() {
    if ($this->type === 'png') {
      // imagepng() expects quality (compression) in range of 0-9.
      return min(9, round($this->quality / 10));
    } else {
      // 0-100, inclusive.
      return $this->quality;
    }
  }

  function watermarkOptions() {
    if ($mark = $this->watermark) {
      $count = isset($mark['count']) ? $mark['count'] : 1;

      if ($count == 1) {
        return array($mark);
      } elseif ($count > 0) {
        $result = array_fill(0, $count, $mark);
        foreach ($result as $i => &$mark) { $mark['y'] = 0.3 * $i + 0.3; }
        return $result;
      }
    }
  }
}