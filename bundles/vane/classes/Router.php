<?php namespace Vane;

// Bootstrapping class providing routines for routing requests into the Vane subsystem.
class Router {
  // function ( [$servers,] $layout = array() )
  //
  // Handler constructing page from scratch with given layout blocks.
  //
  //* $servers array, str catch-all server - controllers handling specific HTTP
  //  request methods - see serve() for format and examples.
  //* $layout hash, str single handler - layout blocks in regular layout.php format.
  //
  //= Closure
  static function layout($servers = array(), $layout = null) {
    return static::layouts(array(), $servers, $layout);
  }

  // function ( $layouts, [$servers,] $layout = array() )
  //
  // Similar to layout() but creates page based on $layouts blocks, optionally
  // altering them with $layout.
  //
  //* $layouts array, str single layout - list of config names defined in layouts.php.
  //* $servers array, str catch-all server - controllers handling specific HTTP
  //  request methods - see serve() for format and examples.
  //* $layout hash, str single handler - altering blocks in regular layout.php format.
  //
  //= Closure
  //
  //? $handler = Vane\Router::layouts('help', 'post help@add', array())
  //  Route::any('help/(:any)', $handler)
  static function layouts($layouts, $servers = array(), $layout = null) {
    if ($layout === null) {
      $layout = $servers;
      $servers = array();
    }

    $self = get_called_class();

    return function () use ($self, $layouts, $servers, $layout) {
      return Layout
        ::fromConfig($layouts)
        ->alter($layout)
        ->served( $self::serve($servers, func_get_args()) )
        ->response();
    };
  }

  //* $servers str, array - members with string keys are (space-separated method
  //  list) => 'ctl@actn ...'; with integer keys - single-method 'post ctl@actn ...'.
  //  Method can be '*' to create a catch-all server matching if no specific
  //  method has matched.
  //* $args mixed - to pass to the server (if matched) when executing its method.
  //= null if nothing appropriate, Laravel\Response
  //
  //? serve('post help@add')        // one server for POST -> help's add() method
  //? serve('* help@show')          // catch-all -> help's show()
  //? serve('help@show')            // the same
  //? serve(array('*' => 'help@show'))    // the same
  //? serve(array('post put' => 'help@add'))
  //      // help's add() handling POST and PUT
  //? serve('post put help@add')
  //      // wrong - 'put' is controller name and 'help@add' - its argument
  //      // similarly to this request URL: /put/index/help@add
  //? serve(array('post help@add', 'put help@edit'))
  //      // help's add() handling POST and edit() handling PUT
  //? serve(array('help@show', 'post help@add', 'put help@edit'))
  //      // like the above with show() handling all but POST and PUT requests
  static function serve($servers, $args = array()) {
    $server = null;
    $key = strtolower(\Request::method());

    foreach ((array) $servers as $method => $handler) {
      if (is_int($meyhod) and strrchr($handler, ' ') !== false) {
        list($method, $handler) = explode(' ', $handler, 2);
      }

      if (is_int($meyhod) or $method === '*') {
        $method = null;
      }

      if (in_array($key, explode(' ', $method))) {
        $server = $handler;
        break;
      } elseif (!$method and !$server) {
        $server = $handler;
      }
    }

    if ($servers) { return Block::execResponse($servers, $args); }
  }
}