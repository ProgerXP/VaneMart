<?php namespace Vane;

class Mail extends \MiMeil {
  static $eventPrefix = 'vane::mail: ';

  //= null, Laravel\View
  public $view;

  static function initMiMeil() {
    $prefix = static::$eventPrefix;

    static::$onEvent = function ($event, $args) use ($prefix) {
      \Event::until($prefix.$event, $args);
    };

    static::registerEventsUsing(array(get_called_class(), 'listen'));

    // saves outgoing messages in a local folder if enabled in the config.
    static::listen('transmit', function (&$subject, &$headers, &$body, $mail) {
      if ($path = $mail->echoPath) {
        $mail->SaveEML($subject, $headers, $body, rtrim($path, '\\/').'/');
      }
    });
  }

  static function listen($event, $callback) {
    \Event::listen(static::$eventPrefix.$event, $callback);
  }

  //= MiMeil on successful transmission, null on error
  static function sendTo($recipients, $view, array $vars) {
    $mail = static::compose($view, $vars);

    if (!$mail->subject) {
      throw new Error('No message subject sent by the e-mail template.');
    }

    $mail->to = arrize($recipients);

    if (!$mail->Send()) {
      Log::warn_Mail("Cannot send e-mail message to ".join(', ', $mail->to).".");
    } else {
      return $mail;
    }
  }

  // Creates a blank message with HTML body set to rendered $view.
  static function compose($view, array $vars) {
    is_object($view) or $view = \View::make($view);

    return static::make()
      ->initView( $view->with($vars) )
      ->Body('html', trim($view->render()));
  }

  // Creates a blank message.
  static function make($to = '', $subject = '') {
    return new static($to, $subject);
  }

  // overriden MiMeil's MIME detector - using Laravel's native facility.
  function MimeByExt($ext, $default = true) {
    $default = $default ? self::$defaultMIME : $ext;
    return \File::mime($ext, $default);
  }

  protected function init() {
    foreach (Current::config('mail') as $prop => $value) {
      $this->$prop = $value;
    }
  }

  function initView(\Laravel\View $view) {
    $this->view = $view;

    $view->data = $view->data + array(
      'mail'              => $this,
      'styles'            => array(),
      'header'            => array(),
      'footer'            => array(),
    );

    $this->defaultViewDataTo($view->data);
    return $this;
  }

  protected function defaultViewDataTo(array &$data) {
    $info = Current::config('company');

    $logoFile = assetPath($info['logo']);
    $this->attachRelatedLocal($logoFile, $name = 'head-logo'.S::ext($logoFile));
    $info['logo'] = "cid:$name";

    $data['header']['logo'] = Block::execResponse('Vane::logo', null, $info)->render();

    $info = array_filter(Current::config('company'), 'is_scalar');
    $signature = __('vanemart::general.mail.signature', $info);
    $data['footer']['signature'] = HLEx::p(\HTML::link(Current::bundleURL(), $signature));
  }

  function attachLocal($path, $name, $options = array()) {
    if (!is_file($path)) {
      Log::error_Mail("Attachment file [$path] doesn't exist - ignoring attachment.");
    } else {
      $options = arrize($options, 'mime') + array(
        'name'            => $name,
        'mime'            => null,
        'headers'         => array(),
        'related'         => false,
      );

      $this->Attach($name, file_get_contents($path), $options['mime'],
                    $options['headers'], $options['related']);
    }

    return $this;
  }

  function attachRelatedLocal($path, $name, $options = array()) {
    $options = arrize($options, 'mime') + array('related' => true);
    return $this->attachLocal($path, $name, $options);
  }

  function styleLocal($path = null) {
    if ($view = $this->reqView()) {
      if (!$path) {
        $path = S::newExt($view->path, '.css');
      } elseif (strpos($path, '::') !== false) {
        list($bundle, $path) = explode('::', $path, 2);
        $path = \Bundle::path($bundle).'views'.DS.str_replace('.', DS, $path).'.css';
      }

      if (is_file($path)) {
        $view->data['styles'][] = file_get_contents($path);
      } else {
        Log::warn_Mail("Stylesheet file [$path] doesn't exist - ignoring.");
      }
    }

    return $this;
  }

  //= null if unassigned, Laravel\View
  function reqView() {
    if ($this->view) {
      return $this->view;
    } else {
      Log::warn_Mail("E-mail message has no associated template (View).");
    }
  }

  function subject($lang, $vars = array()) {
    $this->subject = Str::format(Current::lang($lang), $vars);
    $this->view and $this->view->subject = $this->subject;
    return $this;
  }

  function __call($name, $params) {
    if (ltrim($name[0], 'a..z') === '') {
      return call_user_func_array(array($this, ucfirst($name)), $params);
    } else {
      throw new \BadMethodCallException(get_class($this)."->$name() has no instance".
                                        " method [$name].");
    }
  }
}

Mail::initMiMeil();