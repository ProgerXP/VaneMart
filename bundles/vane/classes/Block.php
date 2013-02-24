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

  // Executes block controller and converts its result to Response. See exec()
  // for the description of arguments.
  //= Laravel\Response
  static function execResponse($block, $args = array(), array $input = null) {
    $response = static::exec($block, $args, $input);
    return with(new static)->toResponse($response);
  }

  // Executes block controller returning whatever value it has produced.
  // For Px\DoubleEdge (and thus Vane\Block) this will always be a Laravel\Request
  // object, for other controllers this can be an arbitrary value.
  //* $block str - regular reference in form '[bundle::]ctl[.sub[....]][@[actn]]'.
  //* $args array, scalar becomes array - to pass to the controller; 1st member is
  //  passed as the method's 1st argument, 2nd - as 2nd, etc.
  //* $input null, hash - optionally set controller's input (options) to this
  //  if it inherits from Vane\Block.
  //= mixed
  //
  //? exec('vanemart::cart@add', array(33, 2.5), array('clear' => 1))
  //      // rough equivalent of doing cart/add/33/2.5?clear=1
  static function exec($block, $args = array(), array $input = null) {
    $obj = static::factory($block);
    ($obj instanceof static) and $obj->input = $input;
    return $obj->execute(static::actionFrom($block), \Px\arrizeAny($args));
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
    $response->breakout = $this->breakout;

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
  function in($var, $default = null) {
    $value = isset($this->input) ? array_get($this->input, $var) : Input::get($var);

    if (isset($value)) {
      return $value;
    } elseif (func_num_args() > 1) {
      return $default;
    } else {
      throw new \Px\ENoInput($var);
    }
  }
}