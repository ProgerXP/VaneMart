<?php namespace VaneMart;

class User extends BaseModel implements \Vane\UserInterface {
  static $table = 'users';
  static $hasURL = true;

  static function findOrCreate(array $info) {
    static $fields = array('name', 'surname', 'phone', 'email');

    $model = User::where('email', '=', $info['email'])->first();

    if ($model) {
      return $model;
    } else {
      $password = Str::password(\Config::get('vanemart::password'));

      $model = with(new User)
        ->fill_raw(array_intersect_key($info, array_flip($fields)))
        ->fill_raw(array(
          'reg_ip'        => Request::ip(),
        ));

      // it won't be hashed if set via fill_raw().
      $model->password = $password;

      if ($model->save()) {
        return S::listable(compact('model', 'password'));
      } else {
        throw new Error('Cannot register new user on checkout.');
      }
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

      return (bool) ($allBut ^ $matched);
    }
  }

  function emailRecipient() {
    return $this->name.' '.$this->surname.'<'.$this->email.'>';
  }
}
User::$table = \Config::get('vanemart::general.table_prefix').User::$table;