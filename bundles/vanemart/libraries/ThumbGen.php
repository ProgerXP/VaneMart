<?php
/*
  Thumbnail generator class | by Proger_XP
  in public domain | http://proger.i-forge.net/ThumbGen
*/

class ThumbGen {
  static $buffer = 8192;
  static $tempDirPerms = 0775;

  protected $source;
  protected $sourceType;
  protected $tempPath;
  protected $temp;
  protected $type = 'jpg';
  protected $quality = 75;
  protected $remoteCacheTTL = 86400;
  protected $watermark;
  protected $cacheFile;

  protected $width = 0, $height = 0;
  protected $widthMin = 1, $heightMin = 1;
  protected $widthMax = 5000, $heightMax = 5000;
  protected $step = 1;
  protected $stepUp = false;
  protected $contain = true;

  static function wrongArg($func, $msg) {
    throw new InvalidArgumentException(get_called_class()."->$func: $msg");
  }

  static function calcDimensionsFor($file, $maxWidth, $maxHeight, $contain = true) {
    list($width, $height) = getimagesize($file);
    $func = $contain ? 'min' : 'max';
    $ratio = $func($maxWidth / $width, $maxHeight / $height);
    return array($width * $ratio, $height * $ratio);
  }

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

    list($thumbWidth, $thumbHeight) = static::calcDimensionsFor($file, $maxWidth, $maxHeight);
    $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);

    if (!$thumb) {
      static::wrongArg(__FUNCTION__, 'imagecreatetruecolor() has failed.');
    }

  imagealphablending($thumb, false);
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

    $output = 'image'.$options[ isset($options['outFormat']) ? 'outFormat' : 'inFormat' ];

    if (!function_exists($output) or !$output($thumb, $options['saveTo'], $options['outQuality'])) {
      throw new RuntimeException(get_called_class().": $output has failed.");
    }

    if (empty($options['returnThumb'])) {
      imagedestroy($thumb);
    } else {
      return $thumb;
    }
  }

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

  function __construct($source, $width = null, $height = null) {
    $this->source = "$source";
    $this->size($width, $height);
    $this->temp(rtrim(sys_get_temp_dir(), '\\/').'/thumbgen');
  }

  function __destruct() {
    $this->temp and @unlink($this->temp);
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
    $this->contain = (bool) $contain;
    return $this;
  }

  function fill($enable = true) {
    return $this->contain(!$enable);
  }

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

  function isLocal() {
    return strrchr($file = $this->source, ':') === false;
  }

  function cacheFile($set = null) {
    if ($set) {
      $this->cacheFile = $set;
      return $this;
    } elseif ($this->cacheFile) {
      return $this->cacheFile;
    } else {
      return $this->tempPath.md5($this->source).
             "-{$this->width}-{$this->height}.{$this->ext()}";
    }
  }

  function ext() {
    return $this->type;
  }

  /*-----------------------------------------------------------------------
  | ACTIONS
  |----------------------------------------------------------------------*/

  //= str path to scaled file
  function scaled() {
    if (!$this->upToDate()) {
      $this->calcSize();
      $this->normalize();
      $this->upToDate() or $this->generate();
    }

    return $this->cacheFile();
  }

  function upToDate($cacheFile = null) {
    $cacheFile or $cacheFile = $this->cacheFile();

    if (is_file($cacheFile) and $cacheTime = filemtime($file)) {
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

  function calcSize() {
    list($width, $height) =
      static::calcDimensionsFor($this->local(), $this->width, $this->height, $this->contain);

    return $this->size($width, $height);
  }

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

  function local() {
    if ($this->isLocal()) {
      if (!is_file($file)) {
        throw new RuntimeException(get_class($this).": non-existing source file [$file].");
      }

      return $file;
    } elseif ($this->temp) {
      return $this->temp;
    } else {
      do {
        $temp = $this->tempPath.mt_rand().strrchr($file, '.');
      } while (is_file($temp));

      return $this->temp = $this->fetchTo($temp);
    }
  }

  protected function fetchTo($temp) {
    mkdir(dirname($temp), static::$tempDirPerms, true);

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

  function generate() {
    $options = array(
      'saveTo'            => $this->cachedFile(),
      'outFormat'         => $this->type,
      'outQuality'        => $this->quality,
      'watermarks'        => $this->watermarkOptions(),
    );

    $type = $this->sourceType and $options['inFormat'] = $type;

    static::scaleImage($this->local(), $this->width, $this->height, $options);
    return $this;
  }

  function watermarkOptions() {
    if ($mark = $this->watermark) {
      $count = isset($mark['count']) ? $mark['count'] : 1;

      if ($count == 1) {
        return array($mark);
      } elseif ($count > 0) {
        $result = array_fill(0, $count - 1, $mark);
        foreach ($result as $i => &$mark) { $mark['y'] = 0.3 * $i + 0.3; }
        return $result;
      }
    }
  }

  function watermarks($count, $file, $x = 0.5) {
    // $y is set to mean distribution for multiple watermarks.
    $this->watermark($file, 0, $x);
    $this->watermark['count'] = $count;
    return $this;
  }

  function watermark($file, $y = 0.5, $x = 0.5) {
    @list($file, $format) = (array) $file;
    if (!$format) { unset($format); }
    $this->watermark = compact('file', 'x', 'y');
    return $this;
  }
}