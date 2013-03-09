<?php namespace VaneMart;

use Auth;

class Block_User extends BaseBlock {
  const PASSWORD_RULE = 'required|min:6|max:50';

  /*---------------------------------------------------------------------
  | GET user/index
  |
  | Shows login form.
  |--------------------------------------------------------------------*/
  function get_login() {
    return true;
  }

  /*---------------------------------------------------------------------
  | POST user/index
  |
  | Attempts to log the user into the system. Does nothing if he's
  | already authorized.
  |----------------------------------------------------------------------
  | * back=URL      - optional; URL to return to after successful login.
  | * remember=0    - optional; remember login in this browser or not.
  | * to_reg=0      - optional; if set the user is redirected with the
  |   input to GET user/reg instead of being attempted to log in.
  |--------------------------------------------------------------------*/
  function post_login() {
    if ($this->in('to_reg', false)) {
      return Redirect::to_route('vanemart::register')->with_input();
    } elseif ($this->ajax()) {
      return static::back(route('vanemart::orders'));
    } else {
      return $this->layout->with('ok', false);
    }
  }

  function ajax_post_login() {
    if (!Auth::check()) {
      $credentials = array('username' => $this->in('email')) +
                     Input::only(array('password', 'remember'));
      Auth::attempt($credentials);
    }

    return Auth::check();
  }

  /*---------------------------------------------------------------------
  | GET user/index
  |
  | Shows registration form.
  |--------------------------------------------------------------------*/
  function get_reg() {
    return true;
  }

  /*---------------------------------------------------------------------
  | POST user/index
  |
  | Attempts to log the user into the system.
  |----------------------------------------------------------------------
  | * back=URL      - optional; URL to return to after successful signup.
  | * to_login=0    - optional; if set the user is redirected with the
  |   input to GET user/login instead of attempting to being registered.
  |--------------------------------------------------------------------*/
  function post_reg() {
    if ($this->in('to_login', false)) {
      return Redirect::to_route('vanemart::login')->with_input();
    } else {
      $result = Auth::guest() ? $this->ajax() : Auth::user();

      if ($result instanceof Eloquent) {
        Auth::logout();
        Auth::login($result->id);
        return static::back(route('vanemart::orders'));
      } else {
        return $result;
      }
    }
  }

  function ajax_post_reg() {
    $rules = array(
      'name'              => 'required',
      'surname'           => 'required',
      'phone'             => 'required|min:7',
      'email'             => 'required|email',
      'password'          => static::PASSWORD_RULE,
      'referee'           => 'integer',
    );

    $valid = Validator::make($this->in(), $rules);
    $email = $this->in('email', null);

    if ($email and User::where('email', '=', $email)->count()) {
      return Validator::withError('login', 'taken');
    } elseif ($valid->fails()) {
      return $valid;
    } else {
      $user = new User(Input::only(array_keys($rules)));
      $user->referee = null;
      $user->reg_ip = Request::ip();

      if ($referee = $this->in('referee', 0)) {
        $referee = User
          ::where('id', '=', $referee)
          ->or_where('email', '=', $referee)
          ->first();

        $referee and $user->referee = $referee->id;
      }

      if ($user->save()) {
        return $user;
      } else {
        Log::error_UserReg('Cannot save new user\'s entry.');
        return E_SERVER;
      }
    }
  }

  /*---------------------------------------------------------------------
  | GET user/index
  |
  | Logs user out of the system. Does nothing if he's unauthorized.
  |----------------------------------------------------------------------
  | * back=URL      - optional; URL to redirect to.
  |--------------------------------------------------------------------*/
  function get_logout() {
    $this->ajax();
    return static::back();
  }

  function ajax_get_logout() {
    Auth::logout();
    return true;
  }
}