<?php namespace Vane;

// Promotes references() scope to public so it can be used by Route::references().
class OpenController extends \Controller {
  static function references(&$url, &$params) {
    return parent::references($url, $params);
  }
}

// Bootstrapping class providing routines for routing requests into the Vane subsystem.
class Route {
  // Name of the key used to store this instance in the registered route (along
  // with 'as', 'https' and other parameters).
  const OPTION = 'vaneRoute';

  //= str URL pattern
  public $url;

  // Global method(s) that this route will be registered for; servers won't response
  // to those not listed here.
  //= str 'get[, post[, ...]]' or '*'
  public $methods = '*';

  // Common parameters are 'as' (route name), 'https' (true/false), 'before' and
  // 'after' (filters).
  //= hash route parameters like 'as' or 'https'
  public $parameters = array();

  // Members with string keys are 'space-separated method list' => 'ctl@actn[ args]',
  // with integer keys - single-method 'post ...'. A method can be '*' to create a
  // catch-all server matching if no more specific method has matched. 'args' is
  // optional space-separated list of arguments to prepend before URL slugs when
  // passing them to the controller's method.
  //
  //= array of str like 'post help@add'
  //
  //? 'post help@add'             // one server for POST -> help's add() method
  //? 'post help@add group'
  //      // the same but passes 'group' as add()'s first argument passing URL
  //      // slugs (if any) after it
  //? '* help@show'               // catch-all -> help's show()
  //? 'help@show'                 // the same
  //? array('*' => 'help@show')   // the same
  //? array('post put' => 'help@add')
  //      // help's add() handling POST and PUT
  //? array('post put' => 'help@add group')
  //      // the same but 'group' is passed before URL slugs to add()
  //? 'post put help@add'
  //      // wrong - 'put' is controller name and 'help@add' - its argument
  //      // similarly to this request URL: /put/index/help@add
  //? array('post help@add', 'put help@edit')
  //      // help's add() handling POST and edit() handling PUT
  //? array('help@show', 'post help@add', 'put help@edit')
  //      // like the above with show() handling all but POST and PUT requests
  public $servers = array();

  //= array of str layout names
  public $baseLayouts = array();

  //= hash layout alterations
  public $layout = array();

  // If set this route is similar to normal Laravel route - a controller is called
  // and its response is returned without using Vane Layout mechanism.
  public $naked = false;

  //= null, Layout last used to generate route response
  public $lastLayout;

  //= null, Block
  public $lastServer;

  //= null, array URL slugs with possible prefixed args from the server string
  public $lastArgs;

  //= Closure to be given to Laravel's Router (it can't handle array callables)
  protected $closure;

  static function references(&$url, &$params) {
    // 1st param is often used to refer to controller's method like 'ctl@(:1)'.
    $params = ((array) $params) + array('', '', '');
    OpenController::references($url, $params);
    return $url;
  }

  //= null, str
  static function findServerIn($servers, $method) {
    $catchAll = null;
    $key = strtolower($method);

    foreach ((array) $servers as $method => $handler) {
      if (is_int($method) and strrchr($handler, ' ') !== false) {
        list($method, $handler) = explode(' ', $handler, 2);

        if (ltrim($method, 'a..zA..Z') !== '') {
          // this isn't a HTTP method name, e.g.: 'my@ctl arg a-2'.
          $handler = "$method $handler";
          $method = '*';
        }
      }

      if (is_int($method) or $method === '*') {
        $method = null;
      }

      if (in_array($key, explode(' ', $method))) {
        return $handler;
      } elseif (!$method and !$catchAll) {
        $catchAll = $handler;
      }
    }

    return $catchAll;
  }

  static function assign($name, $url, $https = null) {
    \Router::find('');    // initialize $names by routing other bundles.

    if (isset( \Router::$names[$name] )) {
      Log::warn_Route("Reassigning existing named route [$name] to [$url].");
    }

    \Router::$names[$name] = array($url => compact('https'));
  }

  //* $url str - of form '[METHOD ]url/...' - see __construct().
  //
  //? Route::on('help/(:any)')->as('routeName')
  //? Route::on('GET help/(:any)')        // only registers the GET route
  //? Route::on('help/(:any)')->methods('get, post')
  static function on($url) {
    $route = new static($url);

    if ($route->registered()) {
      Log::warn_Route("Overwriting registration of [$url].");
    }

    return $route->register();
  }

  static function map($url, $servers, $name = null) {
    $name === true and $name = strtolower(strtok(head((array) $servers), '@'));

    return static
      ::on($url)
      ->as($name)
      ->servers($servers)
      ->layout(array('+#content' => '!'));
  }

  //= null, Route
  static function current() {
    $route = \Request::$route;
    if ($route and $route = array_get($route->action, static::OPTION)) {
      return $route;
    }
  }

  //= null, Route
  static function find($name) {
    return array_get(head((array) \Router::find($name)), static::OPTION);
  }

  //= Route
  static function mustFind($name) {
    if ($route = static::find($name)) {
      return $route;
    } else {
      throw new Error("Cannot find Vane route [$name].");
    }
  }

  //* $url str - of form '[METHOD ]url/...' - METHOD can be lower case. If omitted
  //  defaults to '*' (all). Set multiple methods by calling methods('get, post').
  function __construct($url) {
    @list($method, $rest) = explode(' ', $url, 2);

    if (isset($rest) and in_array(strtoupper($method), \Router::$methods)) {
      $url = $rest;
      $this->methods = $method;
    }

    $this->url($url);

    $self = $this;
    $this->closure = function () use ($self) {
      return call_user_func_array(array($self, 'call'), func_get_args());
    };
  }

  //= bool indicating if a route with given method and URL is registered in Laravel
  function registered($methods = null) {
    $methods or $methods = $this->methods;
    is_array($methods) or $methods = explode(',', $methods);
    $prop = ($this->url and $this->url[0] === '(') ? 'fallback' : 'routes';

    foreach ($methods as $method) {
      if (isset( \Router::${$prop}[$method][$this->url] )) {
        return true;
      }
    }
  }

  //* $methods null get $this->methods, array of str, str like 'get[, post[, ...]]'
  function register($methods = null) {
    \Router::register($methods ?: $this->methods, $this->url, $this->toArray());
    return $this;
  }

  function toArray() {
    return array_merge(array($this->closure, static::OPTION => $this), $this->parameters);
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

  function naked($servers = null) {
    if (func_num_args()) {
      $this->naked = isset($servers);
      return $this->servers($servers);
    } else {
      return $this->naked;
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

  function __get($parameter) {
    return array_get($this->parameters[$parameter]);
  }

  // Calls this route returning producted response. Handles attached layout unless
  // this route is naked() - in this case it's very similar to registering handler
  // with the regular Laravel Router.
  //= Laravel\Response
  function call() {
    $slugs = func_get_args();

    if ($this->naked) {
      return $this->serve($slugs);
    } else {
      $this->lastLayout = Layout
        ::fromConfig($this->baseLayouts)
        ->alter($this->layout)
        ->slugs($slugs);

      return $this->lastLayout->served($this->serve($slugs))->response();
    }
  }

  //* $args mixed - to pass to the server (if matched) when executing its method.
  //= null if nothing appropriate, Laravel\Response
  function serve($args = array()) {
    if ($server = $this->findServer()) {
      // replace (:N) patterns inside controller string, e.g.: 'user@(;1)'.
      static::references($server, $args);

      // prepend arguments that can be defined in the server string after
      // a space: 'help@show contacts'.
      $server = strtok($server, ' ');
      $prepend = ''.strtok(null);
      $prepend === '' or $args = array_merge(explode(' ', $prepend), $args);

      // construct server instance (either a Block or a regular Controller).
      $this->lastArgs = $args;
      $block = $this->lastServer = Block::factory($server);

      // if server is not a traditional controller but a Vane block - set it up.
      if ($block instanceof Block) {
        $block->top = $this->lastLayout;
        $block->title === true and $block->title = array();
        $block->isServer = true;
      }

      // produce response (can be of arbitrary type).
      $this->lastArgs = $args;
      $response = $block->execute(Block::actionFrom($server), $args);

      // convert response to a Laravel\Response descendant.
      return with(new Block)->toResponse($response);
    }
  }

  function findServer($method = null) {
    return static::findServerIn($this->servers, $method ?: \Request::method());
  }
}