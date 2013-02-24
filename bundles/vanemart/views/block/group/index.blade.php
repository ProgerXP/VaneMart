@foreach ($rows as $product)
  <a href="{{ e($product->url()) }}">

    @if ($product->image)
      {{ Px\HLEx::tag('img', array('src' => $product->image(300), 'alt' => $product->title)) }}
    @endif

    <b>{{ e($product->title) }}</b>

  </a>
@endforeach