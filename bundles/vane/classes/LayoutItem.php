<?php namespace Vane;

// Base class representing layout building block, such as handler or nested group.
abstract class LayoutItem {
  public $classes = array();
  public $size;
  public $tag;
  public $style;
  public $attributes = array();

  static function splitClasses($classes, $separator = '.') {
    if (is_array($classes)) {
      return $classes;
    } else {
      return array_filter( explode($separator, "$classes") );
    }
  }

  static function classesMatch($subject, $mustHave) {
    $subject = static::splitClasses($subject);
    $mustHave = static::splitClasses($mustHave);

    return $mustHave ? !array_diff($mustHave, $subject) : !$subject;
  }

  function matches($classes) {
    return static::classesMatch($this->classes, $classes);
  }

  function extractTag(&$str) {
    if (strrchr($str, '/') !== false) {
      list($tag, $str) = explode('/', $str, 2);
      return $tag;
    }
  }

  function extractTagTo(&$tag, &$str) {
    $tag = $this->extractTag($str) ?: $tag;
  }

  function fullID() {
    return join('.', $this->classes);
  }

  function openTag() {
    if ($this->tag) {
      return "\n".HLEx::tag($this->tag, $this->tagAttributes());
    }
  }

  function tagAttributes() {
    $classes = array_except(array_flip($this->classes), '');
    $size = $this->size;
    $width = '';

    if (!$size) {
      // do nothing.
    } elseif (ltrim($size, '0..9') === '') {
      $classes["span-$size"] = true;
    } elseif (ltrim($size[0], '0..9') === '') {
      $width = "width: $size";
    } else {
      $classes["size-$size"] = true;
    }

    return $this->attributes + array(
      'class'           => join(' ', $this->tagClasses( array_keys($classes) )),
      'style'           => trim("$width; ".$this->style, ' ;'),
    );
  }

  protected function tagClasses(array $classes) {
    return $classes;
  }

  function closeTag() {
    if ($this->tag) { return '</'.$this->tag.">\n\n"; }
  }
}