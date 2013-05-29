<?php namespace VaneMart;

abstract class BaseBlock extends \Vane\Block {
  public $bundle = 'vanemart';

  // $inputVar default value is changed from '_back' to 'back'.
  function back($default = '/', $inputVar = 'back') {
    return parent::back($default, $inputVar);
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