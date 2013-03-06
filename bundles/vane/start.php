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
