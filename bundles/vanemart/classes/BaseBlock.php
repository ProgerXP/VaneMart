<?php namespace VaneMart;

class BaseBlock extends \Vane\Block {
  public $bundle = 'vanemart';

  static function back($default = '/') {
    $input = Input::get('back');
    return $input ? Redirect::to($input) : Redirect::back($default);
  }

  protected function makeResponse($response, $internal = true) {
    if ($response === false and $this->isServer) {
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