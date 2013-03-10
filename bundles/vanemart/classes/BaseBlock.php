<?php namespace VaneMart;

class BaseBlock extends \Vane\Block {
  public $bundle = 'vanemart';

  static function back($default = '/') {
    $input = Input::get('back');
    return $input ? Redirect::to($input) : Redirect::back($default);
  }

  protected function afterAction($action, array $params, &$response) {
    if ($response === false and $this->isServer) {
      $response = Redirect
        ::to( route('vanemart::login').'?back='.urlencode(\URI::full()) )
        ->with('passthru', 1);
    }

    return parent::afterAction($action, $params, $response);
  }

  //= null unauthorized, bool
  function can($feature) {
    $user = $this->user(false) ?: Guest::singleton();
    return (bool) $user->can($feature);
  }
}