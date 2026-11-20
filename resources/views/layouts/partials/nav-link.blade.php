{{-- $href, $icon (Font Awesome name), $label, $active (bool), optional $navBadge (count shown when above 0) --}}
<a href="{{ $href }}" class="pcg-nav__link" @if ($active) aria-current="page" @endif>
    <i class="fa-solid fa-{{ $icon }}" aria-hidden="true"></i>
    <span>{{ $label }}</span>
    @if (!empty($navBadge) && is_int($navBadge))<span class="pcg-nav__badge" aria-label="{{ $navBadge }} waiting">{{ $navBadge > 99 ? '99+' : $navBadge }}</span>@endif
</a>
