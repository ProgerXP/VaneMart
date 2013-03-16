<?php namespace Vane;

class Mail extends \MiMeil {
  static $eventPrefix = 'vane::mail: ';

  //= null, Laravel\View
  public $view;

  static function initMiMeil() {
    $prefix = static::$eventPrefix;

    static::$onEvent = function ($event, $args) use ($prefix) {
      return \Event::until($prefix.$event, $args);
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
    \Event::listen($event = static::$eventPrefix.$event, $callback);
    return $event;
  }

  //= MiMeil on successful transmission, null on error
  static function sendTo($recipients, $view, array $vars) {
    $mail = static::compose($view, $vars);

    if (!$mail->subject) {
      is_object($view) and $view = $view->view;
      throw new Error("No message subject set by the e-mail template [$view].");
    }

    $mail->to = arrize($recipients);

    if ($mail->Send()) {
      return $mail;
    } else {
      Log::warn_Mail("Cannot send e-mail message to ".join(', ', $mail->to).".");
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

  // Overriden MiMeil's MIME detector - using Laravel's native facility.
  function MimeByExt($ext, $default = true) {
    $default = $default ? self::$defaultMIME : $ext;
    return \File::mime($ext, $default);
  }

  // Applies default settings from config/mail.php. Called by MiMeil->__construct().
  protected function init() {
    foreach (Current::config('mail') as $prop => $value) {
      $this->$prop = $value;
    }
  }

  // Sets initial view variables regarding message composition to $view. These are
  // accessible to all message templates being rendered.
  function initView(\Laravel\View $view) {
    $this->view = $view;

    $view->data += array(
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

    // Trying to reverse-map the logo URL to local path. Upon success attach that
    // file to the message as "related" file so it's not visible in the attachment
    // list and can be refered to with special "cid:NAME" prefix (e.g. in src).
    // Unlike remote images attached ones are not hidden by most mail viewers.
    $logoFile = assetPath($info['logo']);
    if ($logoFile) {
      $this->attachRelatedLocal($logoFile, $name = 'head-logo'.S::ext($logoFile));
      $info['logo'] = "cid:$name";
    }

    $data['header']['logo'] = Block::execResponse('Vane::logo', null, $info)->render();

    $info = array_filter($info, 'is_scalar') + array(
      'l0'                => HLEx::tag('a', \URL::to(Current::bundleURL())),
      'l1'                => '</a>',
    );

    $signature = __('vanemart::general.mail.signature', $info);
    $data['footer']['signature'] = HLEx::p($signature);
  }

  // Attaches a file from local $path with $name visible to the recipient.
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

  // Similar to attachLocal() but marks the file as "related" - used in HTML
  // decoration and invisible in the attachment list of the mail agent. Unlike
  // normal related attachments can be referred to with "cid:NAME", e.g.:
  // attachRelatedLocal('...', 'name.png');  ->  <img src="cid:name.png">
  function attachRelatedLocal($path, $name, $options = array()) {
    $options = arrize($options, 'mime') + array('related' => true);
    return $this->attachLocal($path, $name, $options);
  }

  // Attaches a stylesheet to this message. If $path is omitted uses locates .css
  // with the same name and directory as the main message template. If $path is
  // given it can be of form [bndl::]path[.file[...]] - relative to bndl's views/
  // or application/views/ if 'bndl::' is omitted or only '::' is present.
  //
  // If stylesheet file cannot be found logs a warning and does nothing.
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
      Log::warn_Mail("E-mail message has no associated template (\$this->view).");
    }
  }

  // Sets message subject.
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