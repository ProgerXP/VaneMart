<?php namespace Vane;

class Error extends \Exception { }

function overrideHTMLki($path, $overrides) {
  $override = function () use ($path, $overrides) {
    $overrides = arrize($overrides, 'ns');

    if (!empty($overrides['ns'])) {
      $overrides += array(
        'compiledHeader'    => "<?namespace $overrides[ns]?>\n",
        'evalPrefix'        => "namespace $overrides[ns];\n",
      );
    }

    \overrideHTMLki($path, $overrides);
  };

  if (\Bundle::started('htmlki')) {
    $override();
  } else {
    \Event::listen('laravel.started: htmlki', $override);
  }
}

if (!\Bundle::exists('plarx')) {
  throw new Error('Vane requires Plarx bundle installed.');
} else {
  \Bundle::start('plarx');
  \Px\Plarx::supersede(__NAMESPACE__, array('Str', 'HLEx'));
}

if (!\Bundle::exists('squall')) {
  throw new Error('Vane requires Squall bundle installed.');
} else {
  \Bundle::start('squall');
  \Squall\initEx(__NAMESPACE__);
}

overrideHTMLki('vane::', __NAMESPACE__);

\Autoloader::namespaces(array(__NAMESPACE__ => __DIR__.DS.'classes'));

// vane::auth[:perm[:perm.2[...]]] [|filter:2 [|...]]
\Route::filter('vane::auth', function ($feature_1 = null) {
  $features = is_array($feature_1) ? $feature_1 : func_get_args();
  $block = is_object(end($features)) ? array_pop($features) : null;
  $user = \Auth::user();

  if ($user and ! $user instanceof UserInterface) {
    $msg = "When using vane::auth filter object returned by Auth::user()".
           " (".get_class($user)." here) must implement Vane\\UserInterface.".
           " This is not so - returned 403 for user {$user->id}.";
    $deny = Log::error_Auth($msg);
  } elseif (!$user) {
    $deny = Log::info_Auth("Block needs authorized user, denying access for guest.");
  } elseif ($missing = array_omit($features, array($user, 'can'))) {
    $s = count($missing) == 1 ? '' : 's';
    $deny = Log::info_Auth("Denied access via vane::auth for user {$user->id} due".
                           " to missing permission$s; ".join(', ', $missing).'.');
  }

  if (!empty($deny)) {
    return $block ? $block->toResponse(false) : false;
  }
});
