<?php namespace Vane;

// Intermediate class storing context of current Layout rendering.
class LayoutRendering {
  public $main;             //= Layout top-level
  public $onlyBlocks;       //= null, array of block classes to include into $result
  public $served;           //= null, Laravel\Response see $main->served()

  // Currently rendered blocks and their wrapping tags.
  //= array '<open>', key => Response, k2 => ..., '</close>', '<op>', ...
  public $result = array();

  static function on(Layout $toRender, Layout $main = null) {
    return static::make($main ?: $toRender)->render($toRender);
  }

  static function make(Layout $main, $onlyBlocks = null) {
    return new static($main, $onlyBlocks);
  }

  function __construct(Layout $main, $onlyBlocks = null) {
    $this->main = $main;
    $this->onlyBlocks = $onlyBlocks;

    if ($main->served() !== null) {
      $this->served = $main->servedResponse();
      $this->served->isServed = true;
    }
  }

  // Renders given layout recursively, adding opening/closing tags and matching
  // blocks against $onlyBlocks.
  function render(Layout $layout) {
    $onlyBlocks = array_flip((array) $this->onlyBlocks);

    foreach ($layout as $block) {
      $matches = null;

      foreach ($onlyBlocks as $classes) {
        $matches = $block->matches($classes);
        if ($matches) { break; }
      }

      if ($matches !== false and (!($block instanceof Layout) or !$block->isView())) {
        $tag = $block->openTag() and $this->result[] = $tag;

        if ($block instanceof Layout) {
          $this->render($block);
        } else {
          $response = $block->isServed() ? $this->served : $block->response();
          $this->result[$this->keyOf($block)] = $response;
        }

        $tag = $block->closeTag() and $this->result[] = $tag;
      }
    }

    return $this;
  }

  // Creates key for given $block that's unique in current result set.
  //= str
  function keyOf(LayoutItem $block) {
    $key = $block->fullID();

    if (isset($this->result[$key])) {
      $i = 1;
      while (isset($this->result[$key.' '.++$i]));
    }

    return $key;
  }

  // Removes block wrapping tags.
  function unwrap() {
    $this->result = \Px\array_keep($this->result, 'is_object');
    return $this;
  }

  //= null if nothing was produced (aka 404), array if $ajax, str otherwise
  function join($ajax = false) {
    if ($this->result) {
      return $ajax ? $this->result : join($this->result);
    }
  }

  // Converts accumulated results into strings.
  function renderResults() {
    foreach ($this->result as $name => &$response) {
      if (is_object($response)) {
        if ($response->headers() and !empty($response->isServed)) {
          Log::warn_Layout("Ignoring headers when inserting response of [$name].");
        }

        $response = $response->render();
      }
    }

    return $this;
  }
}