{{-- Styles: public/css/star-rating.css --}}
<span {{ $attributes->class('star-rating') }}>
    @if ($label !== '')
        <span class="star-rating__label" aria-hidden="true">{{ $label }}</span>
    @endif
    <span class="star-rating__stars" style="--star-rating-fill: {{ $fill }}%" aria-hidden="true"></span>
    <span class="star-rating__number" aria-hidden="true">{{ $number }}</span>
    <span class="star-rating__description">{{ trim($description . ' ' . __(':value out of :max', ['value' => $number, 'max' => $max])) }}</span>
</span>
