<?php namespace Vane;

// Intermediate class storing context of current Layout rendering.
class Rendering {
  static $insignificantHeaders = array('cache-control', 'date');

  public $main;             //= Layout top-level
  public $onlyBlocks;       //= null, array of block classes to include into $result
  public $served;           //= null, Laravel\Response see $main->served()
  public $slugs;            //= null, array of str captured URL parts

  // Currently rendered blocks and their wrapping tags.
  //= array 'blo.ck pa.th' => array('<open>', Response, '</close>'), ...
  public $result = array();

  static function on(Layout $toRender, Layout $main = null) {
    return static::make($main ?: $toRender)->render($toRender);
  }

  static function ignoredHeadersOf(\Laravel\Response $response) {
    $headers = $response->headers()->keys();
    return array_diff($headers, static::$insignificantHeaders);
  }

  static function likeViewVar(Layout $toRender, Layout $main = null, $slugs = null) {
    $rendering = static::make($main)->slugs($slugs)->render($toRender);

    if (count($rendering->result) > 1) {
      return array_values($rendering->joinContents());
    } else {
      return $rendering->join();
    }
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

  function slugs($slugs = null) {
    func_num_args() and $this->slugs = (array) $slugs;
    return func_num_args() ? $this : $this->slugs;
  }

  // Renders given layout recursively, adding opening/closing tags and matching
  // blocks against $onlyBlocks.
  function render(LayoutItem $block, $parent = null, $includeChildren = false) {
    if ($parent === null or ($includeChildren or $this->includes($block))) {
      $includeChildren |= (isset($parent) and $this->onlyBlocks);
      $key = $this->keyOf($block, $parent);

      if (isset($parent)) {
        $this->put($block, "+$key", $block->openTag());
      }

      if ($block instanceof Layout) {
        foreach ($block as $child) { $this->render($child, $key, $includeChildren); }
      } elseif ($block->isServed()) {
        $this->put($block, $key, $this->served);
      } else {
        $block->layout = $this->main;
        $response = $block->response($this->slugs);
        $this->put($block, $key, $response);
      }

      if (isset($parent) and $tag = $block->closeTag()) {
        $this->put($block, "-$key", $tag);
      }
    }

    return $this;
  }

  // Determines if given block should be rendered into the resulting response.
  //= bool
  function includes(LayoutItem $block) {
    if ($block instanceof Layout and $block->isView()) {
      return false;
    } else {
      $matches = null;

      foreach ((array) $this->onlyBlocks as $classes) {
        $matches = $block->matches($classes);
        if ($matches) { break; }
      }

      return $matches !== false;
    }
  }

  // Creates key for given $block that's unique in current result set.
  //= str
  function keyOf(LayoutItem $block, $parent = null) {
    $base = $key = (isset($parent) ? "$parent " : '').$block->fullID();

    if (isset($this->result[$key])) {
      $i = 1;
      while (isset($this->result[$key = $base.' '.++$i]));
    }

    return $key;
  }

  // Saves block data (opening/closing tags or Response) into accumulated results.
  //= $data
  function put(LayoutItem $block, $key, $data) {
    if ($block instanceof Layout) {
      return $this->result[$key] = $data;
    } else {
      return $this->result[ ltrim($key, '+-') ][] = $data;
    }
  }

  // Removes block wrapping tags.
  function unwrap() {
    $this->result = S($this->result)
      ->keep(function ($block) { return is_array($block) or is_object($block); })
      ->map(function ($block) {
        $block = S::keep(arrize($block), 'is_object');
        return count($block) > 1 ? $block : reset($block);
      })
      ->get();

    return $this;
  }

  //= null if nothing was produced (aka 404), array if $ajax, str otherwise
  function join($ajax = false) {
    if ($this->renderResults($ajax)->result) {
      return $ajax ? $this->result : join($this->joinContents());
    }
  }

  // Joins all blocks' contents inside them but not blocks between each other.
  // Returns an array of blocks - their contents joined together.
  //= null if nothing was produced (aka 404), array of str
  function joinContents() {
    if ($this->renderResults()->result) {
      return S($this->result, 'join(Px\\arrize(?))');
    }
  }

  // Converts accumulated results into strings.
  function renderResults($ajax = false) {
    $self = $this;

    $render = function ($response, $name) use ($self, $ajax) {
      if (is_object($response)) {
        if ($ignored = $self::ignoredHeadersOf($response) and
            empty($response->isServed)) {
          Log::warn_Layout("Ignoring headers when inserting response of [$name]: ".
                           join(', ', $ignored).".");
        }

        if ($ajax and $type = $response->headers()->get('Content-Type') and
            ends_with(strtok($type, ';'), 'json')) {
          return json_decode($response->content, true);
        } else {
          return $response->render();
        }
      } else {
        return $response;
      }
    };

    foreach ($this->result as $name => &$items) {
      if (is_array($items)) {
        foreach ($items as &$response) { $response = $render($response, $name); }
      } else {
        $items = $render($items, $name);
      }
    }

    return $this;
  }
}