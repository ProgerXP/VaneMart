<?php namespace VaneMart;

class User extends BaseModel implements \Vane\UserInterface {
  static $table = 'users';
  static $hasURL = true;

  //= str
  static function generatePassword() {
    return (string) Event::result('user.new.password', function ($password) {
      return strlen($password) < 1 ? 'a blank string' : true;
    });
  }

  //= User    if user making the order is already registered
  //= hash    of 'model' (User), 'password' (str) if new account was registered
  static function findOrCreate(array $info) {
    static $fields = array('name', 'surname', 'patronym' , 'city', 'phone', 'email');

    $model = User::where('email', '=', $info['email'])->first();

    if ($model) {
      return $model;
    } else {
      $password = static::generatePassword();

      $model = with(new User)
        ->fill_raw(array_intersect_key($info, array_flip($fields)));

      // it won't be hashed if set via fill_raw().
      $model->password = $password;
      $model->reg_ip = Request::ip();

      $model = Event::insertModel($model, 'user');
      return S::listable(compact('model', 'password'));
    }
  }

  function addresses() {
    return $this->has_many(NS.'Addresses', 'user');
  }

  function orders() {
    return $this->has_many(NS.'Order', 'user');
  }

  function files() {
    return $this->has_many(NS.'File', 'uploader');
  }

  function posts() {
    return $this->has_many(NS.'Post', 'user');
  }

  function referee() {
    return $this->has_one(__CLASS__, 'referee');
  }

  function avatar($width) {
    if ($source = $this->avatar) {
      return Block_Thumb::url(compact('width', 'source'));
    }
  }

  function isPassword($str) {
    return \Hash::check($str, $this->password);
  }

  function set_password($str) {
    $this->set_attribute('password', \Hash::make($str));
  }

  // See Vane\UserInterface::can() for details.
  function can($feature) {
    $perms = $this->perms;

    if (!is_string($feature) or "$perms" === '') {
      return false;
    } elseif ($feature === '') {
      return true;
    }

    $hasWildcards = strrchr(ltrim($perms, '*'), '*') !== false;
    $perms = explode(' ', $perms);
    $allBut = S::unprefix($perms, array('*'));

    if ($feature === '*') {
      // Is this a superuser (who can do anything)?
      return $allBut and !$perms;
    } elseif (!$hasWildcards) {
      return (bool) ($allBut ^ in_array($feature, $perms));
    } else {
      $matched = array_first($perms, function ($i, $perm) use ($feature) {
        return fnmatch($perm, $feature, FNM_NOESCAPE | FNM_PATHNAME | FNM_CASEFOLD);
      });

      return (bool) ($allBut ^ !!$matched);
    }
  }

  function emailRecipient() {
    $self = $this;
    return (string) Event::result('user.recipient', $this, function ($result) use ($self) {
      if (strpos($result, $self->email) === false) {
        return 'a string without the actual e-mail address';
      }
    });
  }
}
User::$table = \Config::get('vanemart::general.table_prefix').User::$table;