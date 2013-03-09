<?php namespace Vane;

// Represents group of blocks, both as a top-level page layout or nested blocks.
class Layout extends LayoutItem implements \IteratorAggregate, \Countable {
  static $blockPrefixes = '.|-=+^';

  public $tag = 'div';

  public $column = false;   //= true for '|block'
  public $row = false;      //= true for '-block'
  public $blocks;           //= array of mixed nested blocks

  protected $served;        //= null, mixed
  protected $slugs = array();   //= array of str URL slugs

  // Returns layout configuration, e.g. array('|top' => 'menu', ...).
  //* $name str layout name, null base layout
  //= null if undefined layout, array blocks, scalar single handler (see parse())
  static function config($name) {
    return Current::config('layouts.', $name);
  }

  // Returns array with keys 'append' (array of Layout (nested blocks) and
  // LayoutHandler (block fillers)) and 'alter' (array of LayoutAlter).
  //
  //* $blocks array of str, array of object preparsed
  //* $get null return all, str key of returned member
  //= array
  static function parse($blocks, $get = null) {
    $append = $alter = array();

    foreach ((array) $blocks as $name => $block) {
      if (is_object($block)) {
        if ($block instanceof LayoutAlter) {
          $alter[] = $block;
        } elseif (! $block instanceof LayoutItem) {
          $block = get_class($block);
          Log::warn_Layout("Invalid object [$block] passed to parse().");
        } else {
          $append[] = $block;
        }
      } elseif (is_int($name)) {
        if (!$block) {
          // it can be empty string as a shortcut to 'empty block' (no handlers).
        } elseif ($block[0] === '=') {
          $append[] = new LayoutHandler('Vane::raw', trim(substr($block, 1)));
        } else {
          $append[] = new LayoutHandler($block, array());
        }
      } elseif ($name and strpbrk($name[0], static::$blockPrefixes) === false) {
        $append[] = new LayoutHandler($name, $block);
      } elseif (!$name or $name[0] === '|' or $name[0] === '-') {
        $append[] = static::from($name, $block);
      } else {
        $alter[] = LayoutAlter::from($name, $block);
      }
    }

    return $get ? $$get : compact('append', 'alter');
  }

  // Creates layout from base layout (null) and altered with given configurations.
  //* $layouts array, str - list of layout names altering the base layout.
  //= Layout
  static function fromConfig($layouts) {
    $layouts = (array) $layouts;

    $obj = static::make( static::config(null) );
    foreach ($layouts as $layout) { $obj->alter(static::config($layout)); }

    return $obj;
  }

  // Creates a nested block object identified by a position.
  //* $position str - list of classes and other presentation features, see __construct().
  //  Can contain leading '|' (for column block) or '-' (for row block).
  //* $blocks str, array - nested blocks, see parse().
  //= Layout
  static function from($position, $blocks) {
    ($blocks instanceof self) and $blocks = $blocks->blocks;
    $obj = new static(ltrim($position, '|-'), $blocks);

    if ($position) {
      $type = $position[0] === '|' ? 'column' : ($position[0] === '-' ? 'row' : null);
      $type and $obj->$type = true;
    }

    return $obj;
  }

  // Creates a top-level layout container - and as such it doesn't have any
  // classes, size or other presentation options used for nested layouts and blocks.
  static function make($blocks) {
    if ($blocks instanceof self) {
      return clone $blocks;
    } else {
      return new static('', $blocks);
    }
  }

  //* $position str - 'class[.class[....]][ size]'.
  //* $blocks str, array - nested blocks, see parse().
  function __construct($position, $blocks) {
    $this->extractTagTo($this->tag, $position);

    list($classes, $size) = explode(' ', "$position ", 2);
    $this->classes = static::splitClasses($classes);
    $this->size = trim($size);

    $this->blocks = arrize($blocks);
  }

  protected function tagClasses(array $classes) {
    $classes and $classes[0] .= '-block';
    $this->column and array_unshift($classes, 'col');
    $this->row and array_unshift($classes, 'row');
    return $classes;
  }

  // function ($slugs)
  // Assigns URL captures - parts in brackets in currently served route URL.
  //* $slugs array, mixed becomes array
  //
  // function ()
  // Returns currently set URL slugs.
  //= array
  function slugs($slugs = null) {
    func_num_args() and $this->slugs = (array) $slugs;
    return func_num_args() ? $this : $this->slugs;
  }

  // function (mixed $data)
  // Sets value for '!' blocks - that is, response specific to some user action.
  // Note that if $data is null it doesn't mean 404 Not Found but just unsets
  // served response indicating this route has no attached server.
  //
  // function ()
  // Returns currently set response, if any.
  //= mixed
  function served($data = null) {
    func_num_args() and $this->served = $data;
    return func_num_args() ? $this : $this->served;
  }

  //= true if this layout defines base view for its container, false otherwise
  function isView() {
    return !$this->classes;
  }

  // Converts all $this->blocks members into LayoutItem objects.
  function parseAll() {
    return $this->setTo($this->blocks);
  }

  //= array of LayoutItem parsed nested blocks
  function children() {
    return $this->parseAll()->blocks;
  }

  function getIterator() {
    return new \ArrayIterator($this->children());
  }

  function count() {
    return count($this->blocks);
  }

  // Applies altering blocks to this layout and then add()'s the rest, if present.
  //* $blocks array, str - list of '-nested' ('|nested'), 'handling' and '=altering'
  //  ('+altering', '^altering') blocks.
  function alter($blocks) {
    $parsed = static::parse($blocks);
    foreach ($parsed['alter'] as $block) { $block->alter($this); }
    return $this->add($parsed['append']);
  }

  // Replaces all nested blocks with given list. See add().
  function setTo($blocks) {
    $this->blocks = array();
    return $this->add($blocks);
  }

  // Adds list of blocks to this layout. If givne, altering blocks are ignored.
  //* $blocks str, array - see parse(). If this layout isView() blocks without
  //  row/column prefixes ('|', '-') in their names become nested Layout's rather
  //  than LayoutHandlers.
  function add($blocks) {
    if ($this->isView() and $blocks = arrize($blocks)) {
      $keys = array_keys($blocks);

      foreach ($keys as &$key) {
        if (!$key or (is_string($key) and
            strpbrk($key[0], static::$blockPrefixes) === false)) {
          $key = "-$key";
        }
      }

      $blocks = array_combine($keys, $blocks);
    }

    $parsed = static::parse($blocks);

    if ($parsed['alter']) {
      $count = count($parsed['alter']);
      $chars = LayoutAlter::chars();
      Log::warn_Layout('Ignoring '.$count.' Alter blocks ("'.$chars.'").');
    }

    $this->blocks = array_merge($this->blocks, $parsed['append']);
    return $this;
  }

  // Treats self as a top-level layout. If a view is specified to be used as
  // wrapper finds it and fills with current data by rendering nested blocks.
  // Each block's class is treated as 'array.path' ($view->data['array']['path']).
  //* $outmost - if set this block is assumed not to contain variables fot the
  //  view but regular blocks processed separately (see render()).
  //= null no view is assigned, View
  function view($outmost = true) {
    if (!$this->isViewEndpoint() and $block = $this->find('')) {
      $view = $block->view(false);
    } else {
      $view = $this->emptyView();
    }

    if ($view and !$outmost) {
      foreach ($this as $block) {
        if ($block instanceof self and ($name = $block->fullID()) !== '') {
          $data = Rendering::likeViewVar($block, $this, $this->slugs);
          array_set($view->data, $name, $data);
        }
      }
    }

    return $view;
  }

  // Indicates that this layout can have immediate view's name or object but no
  // more nested views.
  function isViewEndpoint() {
    return $this->isView() and count($this->blocks) == 1 and
           is_scalar(reset($this->blocks));
  }

  // Low-level function returning a View object if it's specified as one of
  // this layout's child blocks. Returns null if isView() is false or no view
  // name or object was found.
  //= null, View
  function emptyView() {
    $view = $this->isView() ? end($this->blocks) : null;
    while ($view instanceof self) { $view = end($view->blocks); }

    if ($view and is_scalar($view)) {
      return new \View($view);
    } elseif ($view instanceof \Laravel\View) {
      return $view;
    }
  }

  // function ($name)
  // Read view variable.
  //= null if this layout has no emptyView(), mixed
  //
  // function (array $variables)
  // Assign view variables. See single variable version for details.
  //
  // function ($name, $value)
  // Assign single view variable. Does nothing if this layout has no emptyView().
  // Each variable can be prefixed with regular alteration symbol ('+', '^', '=')
  // - if not '=' is assumed. $value is treated as raw data (Block_Raw).
  function viewData($name, $value = null) {
    if (func_num_args() > 1 or is_array($name)) {
      is_array($name) or $name = array($name => $value);

      $alter = S::keys($name, function ($value, $name) {
        if (!isset(LayoutAlter::$typeChars[ $name[0] ])) {
          $name = "=$name";
        }

        return array($name[0].'. '.substr($name, 1), "=$value");
      });

      return $this->alter($alter);
    } elseif (!$this->isViewEndpoint() and $view = $this->find('') and
              $block = $view->find($name)) {
      return Rendering::likeViewVar($block, $this, $this->slugs);
    }
  }

  // Finds nested block.
  //* $path null, str, array of array - each member is an array of class names to match
  //  nested blocks against from left-to-right. If $path is empty (meaning end-point)
  //  $this is returned. If leftmost member is array('*') the first child is matched.
  //  Otherwise, and if it's empty, classesMatch() is used.
  //= null if nothing found, Layout
  function find($path = null) {
    $path = arrize($path);

    if (!$path) {
      return $this;
    } else {
      $classes = array_shift($path);

      if ($classes === array('*')) {
        $matched = head($this->children());
      } else {
        $self = $this;
        $matched = array_first($this, function ($i, $block) use ($self, &$classes) {
          return $block instanceof Layout and
                 $self::classesMatch($block->classes, $classes);
        });
      }

      return $matched ? $matched->find($path) : null;
    }
  }

  // Converts produced served() of arbitrary type to a Response object.
  //= Laravel\Response
  function servedResponse($data = null) {
    if (is_object($this->served) and isset($this->served->server)) {
      $server = $this->served->server;
    } else {
      $server = new Block;
    }

    return $server->toResponse( func_num_args() ? $data : $this->served );
  }

  // Builds a complete response according to layout and client input.
  //= Laravel\Response
  function response() {
    $ajax = Request::ajax();
    $ajax === 'd' and Request::ajax(true);
    $naked = Input::get(Block::$nakedVar, $ajax);

    $onlyBlocks = Input::get('_blocks');
    $singleBlock = (isset($onlyBlocks) and !is_array($onlyBlocks));
    $firstServed = in_array(head((array) $onlyBlocks), array('!', '1', ''));

    if ($singleBlock and !$naked) {
      // preventing DoubleEdge from wrapping response into $fullView.
      Input::merge(array(Block::$nakedVar => 1));
    }

    $rendering = new Rendering($this, $onlyBlocks);
    $rendering->slugs = $this->slugs;

    if (($singleBlock and $firstServed) or $this->breaksout()) {
      isset($this->served) or Log::info_Layout('No specific server on this route.');
      $rendering->result = array($this->servedResponse());
    } else {
      $rendering->render($this);

      if ($singleBlock) {
        $rendering->result = array_slice($rendering->result, 1, -1, true);

        foreach ($rendering->result as $block) {
          foreach (arrize($block) as $response) {
            is_object($response) and $response->isServed = true;
          }
        }
      }
    }

    Request::ajax(null);
    $naked and $rendering->unwrap();

    $response = $rendering->served ?: Response::adapt('');
    $content = $rendering->join($ajax);

    if (!$naked and !$singleBlock and is_scalar($content) and $full = $this->view()) {
      $content = $full->with(compact('content'));
    }

    if (method_exists($response, 'set')) {
      $response->set($content);
    } else {
      $response->content = $content;
    }

    // restoring value that could have been set in the beginning.
    Input::merge(array(Block::$nakedVar => $naked));
    return Response::postprocess($response);
  }

  //= true if served() response should not be embedded into this layout, false otherwise
  function breaksout() {
    if (is_object($this->served) and $this->served->breakout !== false) {
      return $this->served->breakout === true or
             (method_exists($this->served, 'status') and
              ($status = $this->served->status()) >= 300 and $status < 400);
    }
  }
}