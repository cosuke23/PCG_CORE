{{-- $href, $icon (Font Awesome name), $label, $active (bool) --}}
<a href="{{ $href }}" class="pcg-nav__link" @if ($active) aria-current="page" @endif>
    <i class="fa-solid fa-{{ $icon }}" aria-hidden="true"></i>
    <span>{{ $label }}</span>
</a>
