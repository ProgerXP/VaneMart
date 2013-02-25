<div class="vmart-groups">
  @foreach ($rows as $product)
    <div class="product id-{{ $product->id }}">
      <a href="{{ e($product->url()) }}">

        @if ($product->image)
          {{ Px\HLEx::tag('img', array('class' => 'thumb', 'src' => $product->image(300), 'alt' => $product->title)) }}
        @endif

        <b class="title">{{ e($product->title) }}</b>

      </a>

      <p class="info">
        @if ($product->country)
          <span class="country">{{ e($product->country) }}</span>
        @endif
        @if ($product->maker)
          <span class="maker">{{ e($product->maker) }}</span>
        @endif
      </p>

      <p class="price">
        <?php $price = $product->retail ?: $product->wholesale?>

        @if ($price)
          <span class="price">{{ e($price) }}</span>
        @endif

        @if ($product->volume)
          <span class="volume">{{ e($product->volume) }}</span>
        @endif
      </p>
    </div>
  @endforeach
</div>