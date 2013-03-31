<?php namespace Vane;

// Base class for controllers optimized for filling in Vane layout blocks.
class Block extends DoubleEdge {
  //= true should bypass other blocks
  //= false can be inserted into the layout along with others
  //= null autodetect - set to true if Response code is 3xx (redirect)
  public $breakout;

  // Input array that's used instead of global Input, if assigned. See __invoke().
  //= null, hash
  public $input;

  // Layout being rendered. Can be used to set view variables like 'title' or assets.
  //= null, Layout
  public $top;

  // Short way of setting 'title' variable in the $top-view.
  //= null don't do anything
  //= str set to this string
  //= Lang instance - same as string
  //= array set to language variable $this->name + '.' + action + '.title' passing
  //  it replacements in this array; if it's array() the language string is used as is
  public $title = array();

  // Is set if this block is a Route server (aka '!' block).
  public $isServer = false;

  // Executes block controller and converts its result to Response. See exec()
  // for the description of arguments.
  //= Laravel\Response
  static function execResponse($block, $args = array(), $input = array()) {
    return static::execCustom($block, compact('args', 'input') + array(
      'response'          => true,
      'return'            => 'response',
    ));
  }

  // Executes block controller returning whatever value it has produced.
  // For Px\DoubleEdge (and thus Vane\Block) this will always be a Laravel\Request
  // object, for other controllers this can be an arbitrary value.
  //* $block str - regular reference in form '[bundle::]ctl[.sub[....]][@[actn]]'.
  //* $args array, scalar becomes array - to pass to the controller; 1st member is
  //  passed as the method's 1st argument, 2nd - as 2nd, etc.
  //* $input null, hash - optionally set controller's input (options) to this
  //  if it inherits from Vane\Block. If non-null, controller is executed as if
  //  by GET request, if null - both global Input and Request::method() are used.
  //= mixed
  //
  //? exec('vanemart::cart@add', array(33, 2.5), array('clear' => 1))
  //      // rough equivalent of doing cart/add/33/2.5?clear=1
  static function exec($block, $args = array(), $input = array()) {
    return static::execCustom($block, compact('args', 'input') + array(
      'return'            => 'exec',
    ));
  }

  // Flexible block execution returning detailed info about the block and response.
  static function execCustom($block, array $options = array()) {
    $options += array(
      'args'              => array(),
      'verb'              => null,
      'ajax'              => null,
      'input'             => null,
      'layout'            => null,
      'prepare'           => null,
      'response'          => false,
      'return'            => null,
    );

    $input = &$options['input'];

    if (isset($input)) {
      $input = arrize($input);
      isset($options['verb']) or $options['verb'] = 'get';
    }

    $obj = static::factory($block);

    if ($obj instanceof self) {
      $obj->input = $input;
      $obj->top = $options['layout'];
    }

    $options['prepare'] and call_user_func($options['prepare'], $obj);

    // Substituting Symfony's request method as Laravel is explicitly using
    // global Request::method() when executing a controller's method.
    if ($verb = $options['verb'] and $verb != Request::method()) {
      $oldVerb = Request::foundation()->getMethod();
      Request::foundation()->setMethod($verb);
    }

    if (isset($options['ajax'])) {
      $oldAJAX = Request::ajax();
      Request::ajax($options['ajax']);
    }

    try {
      $exec = $obj->execute(static::actionFrom($block), arrizeAny($options['args']));
    } catch (\Exception $e) {
      $exception = $e;
    }

    // Reverting old (current) request state.
    isset($oldAJAX) and Request::ajax($oldAJAX);
    isset($oldVerb) and Request::foundation()->setMethod($oldVerb);

    if (isset($exception)) {
      throw $exception;
    }

    if ($options['response']) {
      $response = with(new static)->toResponse($exec);
    }

    return array_get(compact('obj', 'exec', 'response'), $options['return']);
  }

  // Extracts '@action' part from given controller reference.
  //* $block str, object - like 'vanemart::cart@add'.
  //* $default str - to return if $block is an object or doesn't contain '@...' part.
  //= str
  static function actionFrom($block, $default = 'index') {
    if (is_scalar($block)) {
      @list(, $action) = explode('@', "$block", 2);
    } else {
      $action = null;
    }

    return $action ?: $default;
  }

  // Returns controller instance.
  //* $block object, str - standard Laravel controller reference:
  //  [bndl::]ctl[.subctl][@[actn]]. Bundle, if present, is started and used as
  //  class namespace (so capitalization matters). Subcontrollers become CtlSubctl...
  //  class names. Action part is ignored.
  //* $fail bool - errors if the object doesn't inherit from Laravel\Routing\Controller.
  //= Controller
  //
  //? factory('menu@')->execute('edit', array('main'))
  static function factory($block, $fail = true) {
    if (is_object($block)) {
      $class = $block;
    } else {
      \Bundle::start(\Bundle::resolve( \Bundle::name($block) ));

      if (\IoC::registered($ioc = "vane.block: $block")) {
        $class = $block = IoC::resolve($ioc);
      } else {
        $class = static::classOf($block, false);
        class_exists($class) or $class = static::fromStd($block);
      }
    }

    if ($fail and !is_subclass_of($class, $parent = 'Laravel\\Routing\\Controller')) {
      throw new Error("Block class [$class] must inherit from [$parent].");
    } else {
      return is_object($block) ? $block : new $class;
    }
  }

  // Determines class name that $block controller might have.
  //* $block object, str - controller reference, see factory() for details.
  //* $fail - before returning errors if no such class is defined.
  //= str full class name like NS\\Block_My
  //
  //? classOf('menu')             //=> Block_Menu
  //? classOf('menu@add')         // the same, action part is ignored
  //? classOf('myBun::menu')      //=> myBun\Block_Menu
  //? classOf('myBun::menu.sub')  //=> myBun\Block_MenuSub
  static function classOf($block, $fail = true) {
    $block = strtok($block, '@');
    @list($bundle, $class) = explode('::', $block, 2);

    $class = 'Block_'.ucfirst($class);
    $class = str_replace( ' ', '', ucwords(str_replace('.', ' ', $class)) );

    $bundle === '' or $class = strtok($block, ':')."\\$class";

    if ($fail and !class_exists($class)) {
      throw new Error("Unknown block [$block] - class [$class] is undefined.");
    } else {
      return $class;
    }
  }

  // Creates standard Laravel Controller instance from [bndl::]ctl[sub][@[actn]].
  // Fails with "Class not found" or our Error if there's no such controlller.
  //= Controller
  //
  //? fromStd('user')             //=> User_Controller
  //? fromStd('user@login')       // the same, action part is ignored
  //? fromStd('myBun::user')
  //      //=> User_Controller from bundles/mybun/controllers/user.php
  static function fromStd($controller) {
    list($bundle, $controller) = \Bundle::parse( strtok($controller, '@') );

    if ($obj = \Controller::resolve($bundle, $controller)) {
      return $obj;
    } else {
      throw new Error("Controller class of block [$controller] is undefined.");
    }
  }

  // Unlike default this function appends $this to parameter of each returned
  // filter so that the handler has more information about the block being filtered.
  // For example, 'vane::auth' either redirects the user or outputs a static message
  // depending on whether this block $isServer or not.
  protected function filters($event, $action) {
    $filters = parent::filters($event, $action);

    foreach ($filters as &$collection) {
      $collection = new BlockFilterCollection($this, $collection);
    }

    return $filters;
  }

  // Unlike default doesn't wrap response into $fullView if it's not being explicitly
  // converted to Response object (by calling toResponse()). Also, sets $server
  // and $breakout properties of the returned object to match this instance.
  //= Laravel\Response
  protected function makeResponse($response, $internal = true) {
    if ($internal) {
      $oldView = $this->fullView;
      $this->fullView = null;
    }

    $response = parent::makeResponse($response);
    $response->server = $this;
    empty($response->breakout) and $response->breakout = $this->breakout;

    $internal and $this->fullView = $oldView;
    return $response;
  }

  protected function errorResponse($code = E_SERVER, $data = array()) {
    try {
      return parent::errorResponse($code, $data);
    } catch (\Exception $e) {
      if (strpos($e->getMessage(), 'exist')) {
        return Response::adaptErrorOf('vane::', $code, $this->errorResponseData($data));
      } else {
        throw $e;
      }
    }
  }

  protected function afterAction($action, array $params, &$response) {
    $title = $this->title;

    if (!$this->isServer) {
      // only route server is allowed to set global page data like title.
    } elseif (is_array($title)) {
      $vars = $this->titleVars($this->title, $action);
      $this->viewData('title', $vars['page']);

      $winTitle  = \Lang::line('vanemart::general.window_title', $vars)->get();
      $this->viewData('winTitle', $winTitle);
    } elseif (isset($title) and !is_bool($title)) {
      $this->viewData('title', (string) $title);
    }

    return parent::afterAction($action, $params, $response);
  }

  //= null, array vars
  function titleVars(array $vars, $action) {
    $name = $this->langVarName("$action.title");

    if (\Lang::has($name)) {
      $page = \Lang::line($name, $this->title)->get();
      $vars = compact('page') + \Vane\Current::config('company');
      return S::keep($vars, 'is_scalar');
    }
  }

  function langVarName($var) {
    return str_replace('::block.', '::', $this->name).".$var";
  }

  // function ()
  // Returns all input variables except for files.
  //= hash
  //
  // function ( $var[, $default] )
  // Reads input variable. Uses either global request data or this block's assigned
  // input (options), if available (usually in a subcall from layout).
  // If a single argument is given throws exception if the variable wasn't passed.
  // If two arguments are given and $default is null no error occurs but null is returned.
  //
  //* $var str - input variable name in 'array[.member[....]]' notation.
  //* $default mixed - default value to return if the input doesn't contain $var.
  //= mixed
  //
  //? in('must-present')      // errors if ?must-present variable is not given
  //? in('some-var', 123)     // returns ?some-var's value or 123 if it's not given
  //? in('info.phone', '')    // returns ?info[phone] value or empty string
  function in($var = null, $default = null) {
    $value = isset($this->input) ? array_get($this->input, $var) : Input::get($var);

    if (!func_num_args() or isset($value)) {
      return $value;
    } elseif (func_num_args() > 1) {
      return $default;
    } else {
      throw new \Px\ENoInput($var);
    }
  }

  function viewData($name, $value = null) {
    if ($view = $this->top) {
      return call_user_func_array(array($view, 'viewData'), func_get_args());
    } else {
      Log::warn_Block("Cannot do viewData() - block [{$this->name}] has no".
                      " top-Layout assigned.");
    }
  }

  //= null unauthorized, bool
  function can($feature) {
    if ($user = $this->user(false)) {
      return (bool) $user->can($feature);
    }
  }

  // function ()
  // Read current status string. It may contain unescaped HTML.
  //= str
  //
  // function ($name, $replaces)
  // Set (flash) new status string.
  //* $name str - language string name, See getStatus().
  //* $replaces array, mixed - language string variables. HTML is escaped.
  function status($name = null, $replaces = array()) {
    if (!isset($name)) {
      return \Session::get('status');
    } else {
      $str = $this->getStatus($name, $replaces);
      $str === null or \Session::flash('status', $str);
      return $this;
    }
  }

  //= str with possible HTML entities
  function getStatus($name, $replaces = array()) {
    \Lang::has($name) or $name = $this->langVarName("ctlstatus.$name");

    if (\Lang::has($name)) {
      return HLEx::lang(__($name), $replaces);
    } else {
      Log::warn_Block("Block is missing status language string [$name];".
                      " current action: [{$this->currentAction}].");
    }
  }
}

class BlockFilterCollection {
  public $block;        //= Vane\Block being filtered
  public $collection;   //= Laravel\Routing\Filter_Collection

  function __construct(Block $filtering, $collection) {
    $this->block = $filtering;
    $this->collection = $collection;

    foreach (get_object_vars($collection) as $prop => $value) {
      $this->$prop = &$collection->$prop;
    }
  }

  function __call($method, $params) {
    return call_user_func_array(array($this->collection, $method), $params);
  }

  function get($filter) {
    list($filter, $params) = $this->collection->get($filter);
    $params[] = $this->block;
    return array($filter, $params);
  }
}