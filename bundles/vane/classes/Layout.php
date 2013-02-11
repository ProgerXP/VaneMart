<?php namespace Vane;

// Represents a page layout with means for producing Response objects ready to be sent.
class Layout implements \IteratorAggregate {
  static $blockPrefixes = '|-=+^';

  public $blocks = array();           //= array of LayoutItem
  public $fullView;                   //= null none, LayoutWrapper

  protected $served;                  //= null, mixed

  // Creates layout object from base layout (null) and altered with given named
  // layout configurations.
  //= Layout
  static function fromConfig($layouts) {
    $layouts = (array) $layouts;

    $obj = static::make( static::config(null) );
    foreach ($layouts as $layout) { $obj->alter(static::config($layout)); }

    return $obj;
  }

  // Returns layout configuration, e.g. array('|top' => 'menu', ...).
  //* $name str layout name, null base layout
  //= null if undefined layout, array blocks, scalar single handler (see parse())
  static function config($name) {
    return Current::config('layouts.', $name);
  }

  // Returns array with keys 'append' (array of LayoutBlocks (nested blocks)
  // and LayoutHandler) and 'alter' (array of LayoutAlter).
  //* $blocks array of str, array of object preparsed
  //* $get null return all, str key of returned member
  //= array
  static function parse($blocks, $get = null) {
    $fullView = null;
    $append = $alter = array();

    foreach ((array) $blocks as $name => $block) {
      if ($name === '') {
        $fullView = is_object($block) ? $block : new LayoutWrapper($block);
      } elseif (is_object($block)) {
        if ($block instanceof LayoutAlter) {
          $alter[] = $block;
        } elseif (! $block instanceof LayoutItem) {
          $block = get_class($block);
          Log::warn_Layout("Invalid object [$block] passed to parse().");
        } else {
          $append[] = $block;
        }
      } elseif (is_int($name) or !$name) {
        // $block can be empty string as a shortcut to 'empty block' (no handlers).
        $block and $append[] = new LayoutHandler($block, array());
      } elseif (strpbrk($name[0], static::$blockPrefixes) === false) {
        $append[] = new LayoutHandler($name, $block);
      } elseif ($name[0] === '|' or $name[0] === '-') {
        $append[] = LayoutBlocks::from($name, $block);
      } else {
        $alter[] = LayoutAlter::from($name, $block);
      }
    }

    return $get ? $$get : compact('append', 'alter', 'fullView');
  }

  static function make($blocks) {
    return new static($blocks);
  }

  function __construct($blocks) {
    $this->add($blocks);
  }

  function add($blocks) {
    $parsed = static::parse($blocks);

    if ($parsed['alter']) {
      $count = count($parsed['alter']);
      $chars = LayoutAlter::chars();
      Log::warn_Layout('Ignoring '.$count.' Alter blocks ("'.$chars.'").');
    }

    $this->blocks = array_merge($this->blocks, $parsed['append']);
    return $this->fullView($parsed['fullView']);
  }

  function alter($blocks) {
    $parsed = static::parse($blocks);

    foreach ($parsed['alter'] as $block) {
      $blocks = $block->blocks;
      $matched = $block->findIn($this);

      if (!$matched) {
        Log::info_Layout("No matching block [".$block->pathStr()."] to {$block->type}.");
      } else {
        switch ($block->type) {
        case 'append':
          $matched->blocks = array_merge($matched->blocks, $blocks);
          break;
        case 'prepend':
          $matched->blocks = array_merge($blocks, $matched->blocks);
          break;
        default:
          $matched->blocks = $blocks;
          break;
        }
      }
    }

    $this->add($parsed['append']);
    return $this->fullView($parsed['fullView']);
  }

  function served($data = null) {
    func_num_args() and $this->served = $data;
    return func_num_args() ? $this : $this->served;
  }

  function fullView($new = null) {
    isset($new) and $this->fullView = $new;
    return func_num_args()? $this : $this->fullView;
  }

  //= Laravel\Response
  function servedResponse($data = null) {
    if (is_object($this->served) and $this->served->server) {
      $server = $this->served->server;
    } else {
      $server = new Block;
    }

    return $server->toResponse( func_num_args() ? $data : $this->served );
  }

  //= Laravel\Response
  function response() {
    $onlyBlocks = Input::get('_blocks');
    $rendering = new LayoutRendering($this, $onlyBlocks);

    $singleBlock = (isset($onlyBlocks) and !is_array($onlyBlocks));
    $firstServed = in_array(head((array) $onlyBlocks), array('!', '1', ''));

    if (($singleBlock and $firstServed) or $this->breaksout()) {
      isset($this->served) or Log::info_Layout('No specific server on this route.');
      $rendering->result = array($this->servedResponse());
    } else {
      $rendering->render($this);

      if ($singleBlock) {
        foreach ($rendering->result as &$resp) { $resp->isServed = true; }
      }
    }

    $ajax = \Px\Request::ajax();
    Input::get('_naked', $ajax) and $rendering->unwrap();

    $rendering->renderResults();

    $response = $rendering->served;
    $response->content = $rendering->join($ajax);

    if (is_scalar($response->content) and $full = $this->fullView) {
      $response->content = $full->wrap($response->render());
    }

    return $response;
  }

  //= bool
  function breaksout() {
    if (is_object($this->served) and $this->served->breakout !== false) {
      return $this->served->breakout === true or
             (method_exists($this->served, 'status') and
              ($status = $this->served->status() >= 300 or $status < 400));
    }
  }

  function children() {
    return $this->blocks;
  }

  function getIterator() {
    return new \ArrayIterator($this->children());
  }
}