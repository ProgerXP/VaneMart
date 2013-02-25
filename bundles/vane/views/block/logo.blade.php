<div class="vane-logo">
  @if ($logo)
    <a href="{{ URL::home() }}">
      {{ Px\HLEx::tag('img', array('src' => $logo, 'alt' => $short, 'title' => $long)) }}
    </a>
  @endif

  <p class="short">
    {{ Px\HLEx::a($short, array('href' => Vane\Current::bundleURL())) }}
  </p>

  <p class="motto">{{ e($motto) }}</p>

  @if ($phone)
    <p class="phone">
      @if ($contactsURL)
        {{ Px\HLEx::tag('a', array('href' => $contactsURL)) }}
      @endif

      {{ e($phone) }}

      @if ($contactsURL)
        </a>
      @endif
    </p>
  @endif
</div>