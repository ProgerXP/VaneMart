<h3>{{ $code }}</h3>
<h4>{{ array_get(Symfony\Component\HttpFoundation\Response::$statusTexts, $code) }}</h4>

@if (isset($controller))
  <!-- {{ htmlspecialchars($controller->name.'@'.@$action) }} -->
@endif
