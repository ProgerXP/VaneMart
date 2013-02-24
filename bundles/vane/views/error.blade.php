<h3>{{ $code }}</h3>
<h4>{{ Px\Response::statusText($code) }}</h4>

@if (isset($controller))
  <!-- {{ htmlspecialchars($controller->name.'@'.@$action) }} -->
@endif
