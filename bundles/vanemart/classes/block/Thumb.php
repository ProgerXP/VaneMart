<?php namespace VaneMart;

class Block_Thumb extends BaseBlock {
  static $protectedVars = array('source', 'width', 'height');

  static function url($input) {
    $input = S::arrize($input, 'source');

    if ($source = &$input['source']) {
      S::unprefix($source, File::storage());
      $input['hash'] = static::hash($input);
      return \route('vanemart::thumb').S::queryStr($input);
    }
  }

  static function hash(array $input) {
    $str = \Config::get('application.key')."~vm:thumb";

    foreach (static::$protectedVars as $var) {
      $str .= "\5".array_get($input, $var);
    }

    return rtrim(base64_encode(md5($str, true)), '=');
  }

  //= ThumbGen
  static function configure(\ThumbGen $thumb, array $input, array $options = null) {
    $options === null and $options = \Config::get('vanemart::thumb');
    extract($options, EXTR_SKIP);

    $thumb
      ->temp(\Bundle::path('vanemart').'public/thumbs')
      ->type($type, $quality)
      ->remoteCacheTTL($remoteCacheTTL)
      ->size(array_get($input, 'width', 0), array_get($input, 'height', 0))
      ->restrict('width', $widthMin, $widthMax)
      ->restrict('height', $heightMin, $heightMax)
      ->step($step, array_get($input, 'up'))
      ->fill(array_get($input, 'fill'));

    if ($watermark) {
      if ($watermark['count'] == 1) {
        $thumb->watermark($watermark['file'], $watermark['y'], $watermark['x']);
      } else {
        $thumb->watermarks($watermark['count'], $watermark['file'], $watermark['x']);
      }
    }

    return $thumb;
  }

  function get_index() {
    $source = $this->in('source');
    if ($this->in('hash') !== static::hash($this->in())) {
      return E_DENIED;
    }

    $source = File::storage($source);
    $thumb = static::configure(\ThumbGen::make($source), $this->in());

    $url = $thumb->scaled();
    if (!S::unprefix($url, $thumb->temp())) {
      throw new Exception("Cannot determine thumbnail URL from [$url].");
    }

    return Redirect::to(asset("thumbs/$url"));
  }
}