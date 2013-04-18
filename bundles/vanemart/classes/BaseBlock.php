<?php namespace VaneMart;

class BaseBlock extends \Vane\Block {
  public $bundle = 'vanemart';

  function back($default = '/') {
    return parent::back($default, 'back');
  }

  protected function makeResponse($response, $internal = true) {
    if ($this->isServer and ($response === false or $response === E_UNAUTH) and
        !$this->user(false)) {
      return Redirect
        ::to( route('vanemart::login').'?back='.urlencode(\URI::full()) )
        ->with('passthru', 1);
    } else {
      return parent::makeResponse($response, $internal);
    }
  }

  //= null unauthorized, bool
  function can($feature) {
    $user = $this->user(false) ?: Guest::singleton();
    return (bool) $user->can($feature);
  }
}