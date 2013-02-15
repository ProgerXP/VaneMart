<ul>
  @foreach ($items as $item)
    @if ($item->visible())
      {{ Px\HLEx::tag('li', array( 'class' => $item->classes(), 'title' => trim($item->hint) ?: null )) }}

      @if (isset($item->caption))
        @if ($item->url)
          {{ Px\HLEx::tag('a', array( 'href' => $item->url, 'target' => HLEx::target($item->popup()) )) }}
        @endif

        @if ($item->icon)
          {{ Px\HLEx::tag('img', array('src' => $item->icon, 'alt' => $item->caption)) }}
        @endif

        {{ Px\HLEx::span_q($item->caption) }}

        @if ($item->url)
          </a>
        @endif
      @endif

      @if ($item->html)
        {{ $item->html }}
      @endif
    @endif
  @endforeach
</ul>
