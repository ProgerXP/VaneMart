<?php namespace VaneMart;

use Auth;

class Block_User extends BaseBlock {
  const PASSWORD_RULE = 'required|min:6|max:50';

  // GET user/index           - login form
  //   ?back=URL              - optional; URL to return to after successful login
  //   ?remember=1            - remember login in this browser or not
  //   ?to_reg=1              - if set the user is redirected to user@reg
  function get_login() {
    return true;
  }

  function post_login() {
    if (Input::get('to_reg')) {
      return Redirect::to_action('vanemart::user@reg')->with_input();
    } elseif ($this->ajax()) {
      return static::back(action('vanemart::orders'));
    } else {
      return $this->layout->with('ok', false);
    }
  }

  function ajax_post_login() {
    if (!Auth::check()) {
      $credentials = array('username' => Input::must('email')) +
                     Input::only(array('password', 'remember'));
      Auth::attempt($credentials);
    }

    return Auth::check();
  }

  // GET user/reg             - signup form
  //   ?back=URL              - optional; URL to return to after registration
  //   ?to_login=1            - if set the user is redirected to user@login
  function get_reg() {dd(\Hash::make('a'));
    return true;
  }

  function post_reg() {
    if (Input::get('to_login')) {
      return Redirect::to_action('vanemart::user@login')->with_input();
    } else {
      $result = Auth::guest() ? $this->ajax() : Auth::user();

      if ($result instanceof Eloquent) {
        Auth::login($result->id);
        return static::back(action('vanemart::orders'));
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

    $valid = Validator::make(Input::all(), $rules);

    if (User::where('email', '=', Input::get('email'))->count()) {
      return Validator::withError('login', 'taken');
    } elseif ($valid->fails()) {
      return $valid;
    } else {
      $user = new User(Input::only(array_keys($rules)));
      $user->referee = null;
      $user->reg_ip = Request::ip();

      if ($referee = Input::get('referee')) {
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

  // GET user/logout          - lougs user out of the system
  //   ?back=URL              - optional
  function get_logout() {
    $this->ajax();
    return static::back();
  }

  function ajax_get_logout() {
    Auth::logout();
    return true;
  }
}