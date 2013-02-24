<?php namespace Vane;

// Promotes references() scope to public so it can be used by Route::references().
class OpenController extends \Controller {
  static function references(&$url, &$params) {
    return parent::references($url, $params);
  }
}

// Bootstrapping class providing routines for routing requests into the Vane subsystem.
class Route {
  //= str URL pattern
  public $url;

  // Global method(s) that this route will be registered for; servers won't response
  // to those not listed here.
  //= str 'get[, post[, ...]]' or '*'
  //
  //? 'post help@add'             // one server for POST -> help's add() method
  //? '* help@show'               // catch-all -> help's show()
  //? 'help@show'                 // the same
  //? array('*' => 'help@show')   // the same
  //? array('post put' => 'help@add')
  //      // help's add() handling POST and PUT
  //? 'post put help@add'
  //      // wrong - 'put' is controller name and 'help@add' - its argument
  //      // similarly to this request URL: /put/index/help@add
  //? array('post help@add', 'put help@edit')
  //      // help's add() handling POST and edit() handling PUT
  //? array('help@show', 'post help@add', 'put help@edit')
  //      // like the above with show() handling all but POST and PUT requests
  public $method = '*';

  // Common parameters are 'as' (route name), 'https' (true/false), 'before' and
  // 'after' (filters).
  //= hash route parameters like 'as' or 'https'
  public $parameters = array();

  // Members with string keys are 'space-separated method list' => 'ctl@actn ...',
  // with integer keys - single-method 'post ctl@actn ...'. A method can be '*' to
  // create a catch-all server matching if no more specific method has matched.
  //
  //= array of str like 'post help@add'
  public $servers = array();

  //= array of str layout names
  public $baseLayouts = array();

  //= hash layout alterations
  public $layout = array();

  //= Closure to be given to Laravel's Router (it can't handle array callables)
  protected $closure;

  static function references(&$url, &$params) {
    // 1st param is often used to refer to controller's method like 'ctl@(:1)'.
    $params = ((array) $params) + array('');
    OpenController::references($url, $params);
    return $url;
  }

  //? Route::on('help/(:any)')->as('routeName')
  static function on($url) {
    $route = new static($url);

    if ($route->registered()) {
      Log::warn_Route("Overwriting registration of [$url].");
    }

    return $route->register();
  }

  //= Route
  static function find($name) {
    return array_get(\Router::find($name), 'vaneRoute');
  }

  function __construct($url) {
    $this->url($url);

    $self = $this;
    $this->closure = function () use ($self) {
      return call_user_func_array(array($self, 'call'), func_get_args());
    };
  }

  function register($method = null) {
    $method === null and $method = $this->method;
    \Router::register($method, $this->url, $this->toArray());
    return $this;
  }

  function toArray() {
    return array_merge(array($this->closure, 'vaneRoute' => $this), $this->parameters);
  }

  function url($url = null) {
    if ($url) {
      $this->url = ltrim(str_replace('(:bundle)', \Router::$bundle, $url, $count), '/');

      if ($count and !\Router::$bundle) {
        Log::warn_Route("(:bundle) used in route URL but current bundle name is not".
                        " available - replaced with empty string (produced [$url]).");
      }

      return $this;
    } else {
      return $this->url;
    }
  }

  function __call($method, $params) {
    $params or $params = array(true);

    if (isset($this->$method)) {
      if (is_array($this->$method) and !is_array($params[0])) {
        $this->$method = $params;
      } else {
        $this->$method = reset($params);
      }
    } else {
      $this->parameters[$method] = count($params) > 1 ? $params : reset($params);
      $this->register();
    }

    return $this;
  }

  // Calls this route
  function call() {
    $slugs = func_get_args();

    return Layout
      ::fromConfig($this->baseLayouts)
      ->alter($this->layout)
      ->slugs($slugs)
      ->served($this->serve($slugs))
      ->response();
  }

  //* $args mixed - to pass to the server (if matched) when executing its method.
  //= null if nothing appropriate, Laravel\Response
  function serve($args = array()) {
    $server = null;
    $key = strtolower(\Request::method());

    foreach ((array) $this->servers as $method => $handler) {
      if (is_int($method) and strrchr($handler, ' ') !== false) {
        list($method, $handler) = explode(' ', $handler, 2);
      }

      if (is_int($method) or $method === '*') {
        $method = null;
      }

      if (in_array($key, explode(' ', $method))) {
        $server = $handler;
        break;
      } elseif (!$method and !$server) {
        $server = $handler;
      }
    }

    if ($server) {
      static::references($server, $args);
      return Block::execResponse($server, $args);
    }
  }
}