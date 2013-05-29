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

function aliasIn($ns) {
  $overridenPlarx = \Bundle::option('vane', 'ignorePlarx');
  $ns = trim($ns, '\\');

  foreach ($overridenPlarx as $class) {
    \Autoloader::alias("Vane\\$class", "$ns\\$class");
  }

  Plarx::supersede($ns, $overridenPlarx);
}

//= str local path, null if cannot reverse-map $url
function assetPath($url) {
  $url = asset($url);
  $base = rtrim(\URL::to_asset(''), '/').'/';

  if (S::unprefix($url, $base)) {
    if (strtok($url, '/') === 'bundles') {
      $bundle = strtok('/');
      $path = strtok(null);
      return \Bundle::path($bundle).'public'.DS.ltrim($path, '\\/');
    } else {
      return \path('public').ltrim($url, '\\/');
    }
  }
}
