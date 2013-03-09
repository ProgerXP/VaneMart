<?php namespace VaneMart;

class User extends BaseModel implements \Vane\UserInterface {
  static $table = 'users';
  static $hasURL = true;

  static function findOrCreate(array $info) {
    static $fields = array('name', 'surname', 'phone', 'email');

    $model = User::where('email', '=', $info['email'])->first();
    if (!$model) {
      $password = Str::password(\Config::get('vanemart::password'));

      $model = with(new User)
        ->fill_raw(array_intersect_key($info, array_flip($fields)))
        ->fill_raw(array(
          'password'      => $password,
          'reg_ip'        => Request::ip(),
        ));

      if (!$model->save()) {
        throw new Error('Cannot register new user on checkout.');
      }
    }

    return $model;
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
    $allBut = S::unprefix($perms, '*');

    if ($feature === '*') {
      // Is this a superuser (who can do anything)?
      return $allBut and !$perms;
    } elseif (!$hasWildcards) {
      return 0 != $allBut ^ in_array($feature, $perms);
    } else {
      $matched = array_first($perms, function ($i, $perm) use ($feature) {
        return fnmatch($perm, $feature, FNM_NOESCAPE | FNM_PATHNAME | FNM_CASEFOLD);
      });

      return 0 != $allBut ^ $matched;
    }
  }
}
User::$table = \Config::get('vanemart::general.table_prefix').User::$table;