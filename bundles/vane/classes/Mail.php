<?php namespace Vane;

class Mail extends \LaMeil {
  protected function init() {
    foreach (Current::config('mail') as $prop => $value) {
      $this->$prop = $value;
    }
  }

  protected function defaultViewDataTo(array &$data) {
    $info = Current::config('company');

    // Trying to reverse-map the logo URL to local path. Upon success attach that
    // file to the message as "related" file so it's not visible in the attachment
    // list and can be refered to with special "cid:NAME" prefix (e.g. in src).
    // Unlike remote images attached ones are not hidden by most mail viewers.
    $logoFile = assetPath($info['logo']);
    if ($logoFile) {
      $this->attachRelatedLocal($logoFile, $name = 'head-logo'.S::ext($logoFile));
      $info['logo'] = "cid:$name";
    }

    $data['header']['logo'] = Block::execCustom('Vane::logo', array(
      'input'             => $info,
      'ajax'              => false,
      'response'          => true,
      'return'            => 'response',
    ))->render();

    $info = array_filter($info, 'is_scalar') + array(
      'l0'                => HLEx::tag('a', \URL::to(Current::bundleURL())),
      'l1'                => '</a>',
    );

    $signature = __('vanemart::general.mail.signature', $info);
    $data['footer']['signature'] = HLEx::p($signature);
  }

  function subject($lang, $vars = array()) {
    $this->subject = Str::format(Current::lang($lang), $vars);
    $this->view and $this->view->subject = $this->subject;
    return $this;
  }
}