<?php namespace VaneMart;

class Block_Thumb extends BaseBlock {
  static $protectedVars = array('source', 'width', 'height');

  static function url($input) {
    $input = S::arrize($input, 'source');

    if (!empty($input['source'])) {
      $input['hash'] = static::hash($input);
      return \route('vanemart::thumb').S::queryStr($input);
    }
  }

  static function hash(array $input) {
    $str = \Config::get('application.key')."~vm:thumb";

    foreach (static::$protectedVars as $var) {
      $str .= "\5".array_get($input, $var);
    }

    return base64_encode(md5($str, true));
  }

  //= ThumbGen
  static function configure(\ThumbGen $thumb, array $options) {
    extract($options, EXTR_SKIP);

    $thumb
      ->temp(Bundle::path('vanemart').'public/thumbs')
      ->type($type, $quality)
      ->remoteCacheTTL($remoteCacheTTL)
      ->size($this->in('width', 0), $this->in('height', 0))
      ->restrict('width', $widthMin, $widthMax)
      ->restrict('height', $heightMin, $heightMax)
      ->step($step, $this->in('up'))
      ->fill($this->in('fill'));

    if ($watermark) {
      if ($count == 1) {
        $thumb->watermark($watermark['file'], $watermark['y'], $watermark['x']);
      } else {
        $thumb->watermarks($count, $watermark['file'], $watermark['x']);
      }
    }

    return $thumb;
  }

  function get_index() {
    $source = $this->in('source');
    if ($this->in('hash') !== static::hash($this->in())) {
      return E_DENY;
    }

    $thumb = static::configure(ThumbGen::make($source), \Config::get('vanemart::thumb'));

    $url = $thumb->scaled();
    if (!S::unprefix($url, $thumb->temp())) {
      throw new Exception("Cannot determine thumbnail URL from [$url].");
    }
dd(asset("thumbs/$url"));
    return Redirect::to(asset("thumbs/$url"));
  }
}