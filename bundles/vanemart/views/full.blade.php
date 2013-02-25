<!DOCTYPE html>
<html lang="{{ Config::get('application.language') }}">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title }}</title>

    <meta name="description" content="VaneMart - the flowing e-commerce software.">
    <meta name="robots" content="index,follow">
    <meta name="generator" content="VaneMart">

    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">

    @foreach ($styles as $style => $attributes)
      @if (Request::is_env('local') and substr($style, -5) === '.less')
        <?php $hasLESS = true?>
        <link href="{{ e($style) }}" rel="stylesheet/less">
      @else
        {{ HTML::style($style, $attributes) }}
      @endif
    @endforeach

    <?php if (isset($hasLESS) and Request::is_env('local')) {?>
      <script>var less = {env: 'development'};</script>
      <script type="text/javascript" src="{{ VaneMart\asset('less.js') }}"></script>
    <?php }?>

    <!--[if lt IE 9]>
      <script src="{{ VaneMart\asset('ie9.js') }}"></script>
    <![endif]-->
  </head>
  <body class="{{ e(join(' ', $bodyClasses)) }}">
    @if (isset(Section::$sections['content']))
      @yield('content')
    @else
      {{ $content }}
    @endif

    {{ Asset::scripts() }}
  </body>
</html>