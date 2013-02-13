<?php namespace Vane;

// Intermediate class storing context of current Layout rendering.
class LayoutRendering {
  public $main;             //= Layout, LayoutBlocks
  public $onlyBlocks;       //= null, array
  public $served;           //= Laravel\Response
  // array( '<open>', key => Response, k2 => ..., '</close>', '<op>', ... )
  public $result = array();

  static function make($main, $onlyBlocks = null) {
    return new static($main, $onlyBlocks);
  }

  function __construct($main, $onlyBlocks = null) {
    $this->main = $main;
    $this->onlyBlocks = $onlyBlocks;

    $this->served = $main->servedResponse();
    $this->served->isServed = true;
  }

  function render(\Traversable $layout) {
    $onlyBlocks = array_flip((array) $this->onlyBlocks);

    foreach ($layout as $block) {
      $matches = null;

      foreach ($onlyBlocks as $classes) {
        $matches = $block->matches($classes);
        if ($matches) { break; }
      }

      if ($matches !== false) {
        $tag = $block->openTag() and $this->result[] = $tag;

        if ($block instanceof LayoutBlocks) {
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

  function keyOf(LayoutItem $block) {
    if ($block instanceof LayoutHandler) {
      $key = $block->fullID();
    } else {
      $key = join(' ', $block->classes);
    }

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