<?php namespace VaneMart;

use Auth;
use Laravel\Database\Eloquent\Model as LaravelModel;

class Block_User extends BaseBlock {
  const PASSWORD_RULE = 'required|min:6|max:50';

  /*---------------------------------------------------------------------
  | GET user/index
  |
  | Shows login form.
  |--------------------------------------------------------------------*/
  function get_login() {
    if (Auth::check()) {
      \Session::keep('status');
      return Redirect::to_route('vanemart::orders');
    } else {
      return true;
    }
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
    } elseif ($this->in('to_reset', false)) {
      return Redirect::to_route('vanemart::reset')->with_input();
    } elseif ($this->ajax()) {
      $response = $this->back(route('vanemart::orders'));

      if ($user = Auth::user()) {
        Event::fire('user.login', array(&$response, $user, $this));
      }

      return $response;
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

      if ($result instanceof LaravelModel) {
        Auth::logout();
        Auth::login($result->id);
        return $this->back(route('vanemart::orders'));
      } else {
        return $result;
      }
    }
  }

  function ajax_post_reg() {
    $rules = array(
      'name'              => 'required',
      'surname'           => 'required',
      'phone'             => 'required|min:7|vanemart_phone',
      'email'             => 'required|email',
      'password'          => static::PASSWORD_RULE,
      'referee'           => 'integer',
    );
    $rules += (array) \Vane\Current::config('general.user_fields.user');

    $valid = Validator::make($this->in(), $rules);
    $email = $this->in('email', null);

    if ($email and User::where('email', '=', $email)->count()) {
      return Validator::withError('email', 'vanemart::taken');
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

      return Event::insertModel($user, 'user');
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
    $user = Auth::user();

    $this->ajax();
    $this->status('logout');

    // Not redirecting back() because the user might have been on a protected
    // page (e.g. his orders); returning him there will cause the login form
    // to appear again which may be confusing.
    $response = Redirect::to(\Vane\Current::bundleURL());

    $user and Event::fire('user.logout', array(&$response, $user, $this));
    return $response;
  }

  function ajax_get_logout() {
    Auth::logout();
    return true;
  }

  function get_reset() {
    $this->layout = '.login';
    return $this->post_reset();
  }

  function post_reset() {
    $this->layout = '.login';

    $this->layoutVars = array('defaultReset' => 'default');
    $email = Input::old('email') ?: $this->in('email', null);
    $rules = array(
      'email'             => 'required|email',
    );
    $valid = Validator::make(array('email' => $email), $rules);

    if ($valid->fails()) {
      return $valid;
    }

    $user = User::where('email', '=', $email)->first();
    if (!$user) {
      return array(
        'ok' => false,
        'reset_error' => 'unknown_email',
        'reglink' => route('vanemart::register').'?email='.urlencode(strip_tags($email)),
      );
    }

    $emailHash = $this->encodeValue($email);
    $hash = $user->resetHash();
    $link = route("vanemart::reset_password")."/$emailHash/$hash";

    \Vane\Mail::sendTo($user->emailRecipient(), 'vanemart::mail.user.reset_instructions', array(
      'link'     => $link,
    ));

    $this->status('reset_instructions');
    return $this->back(route('vanemart::login'));
  }

  function get_reset_password($email, $hash) {
    $days = \Vane\Current::config('password.reset_days');

    try {
      $email = $this->decodeValue($email);

      $user = User::where('email', '=', $email)->first();
      if (!$user) {
        throw new Error('User not found');
      }

      $valid = false;
      for ($day = 0; $day <= $days; $day++) {
        if ($user->resetHash($day, $hash)) {
          $valid = true;
          break;
        }
      }

      if (!$valid) {
        throw new Error('Hash is invalid');
      }

      $newPassword = User::generatePassword();
      $user->password = $newPassword;
      $user->save();

      \Vane\Mail::sendTo($user->emailRecipient(), 'vanemart::mail.user.new_password', array(
        'email'         => $email,
        'password'      => $newPassword,
      ));

      $this->status('new_password');
      Auth::login($user->id);
    } catch (\Exception $e) {
      Log::error_User('Error while resetting password: '.$e->getMessage());
      return $this->resetPasswordError();
    }

    return $this->back(route('vanemart::login'));
  }

  protected function resetPasswordError() {
    return Redirect::to_route('vanemart::login')
      ->with('ok', false)->with('reset_error', 'other');
  }

  protected function encodeValue($value) {
    $value = \Crypter::encrypt($value);
    $value = str_replace(array('/', '+', '='), array('_', '-', ''), $value);
    return $value;
  }

  protected function decodeValue($value) {
    $value = str_replace(array('_', '-'), array('/', '+'), $value);
    return \Crypter::decrypt($value);
  }
}