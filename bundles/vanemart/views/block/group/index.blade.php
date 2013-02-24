@foreach ($rows as $product)
  <a href="{{ e($product->url()) }}">

    @if ($product->image)
      {{ HTML::image($product->image(300), $product->title) }}
    @endif

    <b>{{ e($product->title) }}</b>

  </a>
@endforeach