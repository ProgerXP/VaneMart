<div class="vmart-product">
  {{ Px\HLEx::h1($title) }}

  @if ($image)
    {{ HTML::image(e($image), $title, array('class' => 'image')) }}
  @endif

  <table class="info">
    <tr>
      <th>{{ __('vanemart::product.price') }}</th>
      <td>
        @foreach (compact('retail', 'wholesale') as $type => $price)
          @if ($price)
            @if (isset($_hadPrice))
              ,
            @endif
            <?php $_hadPrice = true?>

            {{ Px\HLEx::langNum(__('vanemart::general.price'), $price) }}
          @endif
        @endforeach
      </td>
    </tr>
    @foreach (compact('sku', 'country', 'maker', 'volume') as $type => $value)
      @if ($value)
        <tr>
          <th>{{ __('vanemart::product.'.$type) }}</th>
          <td>{{ e($value) }}</td>
        </tr>
      @endif
    @endforeach
  </table>

  <div class="desc">{{ $desc_html }}</div>
</div>