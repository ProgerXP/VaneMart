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

  function tagID() {
    if ($this->classes and $this->classes[0][0] === '#') {
      return substr($this->classes[0], 1);
    }
  }

  function tagAttributes() {
    $id = $this->tagID();
    $classes = array_diff($this->classes, array(''));
    $id and array_shift($classes);
    $classes = $this->tagClasses($classes);

    $size = $this->size;
    $width = '';

    if (!$size) {
      // do nothing.
    } elseif (ltrim($size, '0..9') === '') {
      $classes[] = "span$size";
    } elseif (ltrim($size[0], '0..9') === '') {
      $width = "width: $size";
    } else {
      $classes[] = "size-$size";
    }

    return $this->attributes + array(
      'id'              => $id,
      'class'           => join(' ', array_unique($classes)),
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