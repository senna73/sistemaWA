@foreach (\App\Support\SidebarMenu::items(auth()->user()) as $item)
  @if (($item['type'] ?? 'link') === 'group')
    <li class="menu-item {{ $item['active'] ? 'active open' : '' }}" data-sidebar-group="{{ $item['id'] }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon tf-icons bx {{ $item['icon'] }}"></i>
        <div>{{ $item['label'] }}</div>
      </a>
      <ul class="menu-sub">
        @foreach ($item['children'] as $child)
          <li class="menu-item {{ $child['active'] ? 'active' : '' }}" data-sidebar-item="{{ $child['id'] }}">
            <a href="{{ $child['url'] }}" class="menu-link">
              <i class="menu-icon tf-icons bx {{ $child['icon'] }}"></i>
              <div>{{ $child['label'] }}</div>
            </a>
          </li>
        @endforeach
      </ul>
    </li>
  @else
    <li class="menu-item {{ $item['active'] ? 'active' : '' }}" data-sidebar-item="{{ $item['id'] }}">
      <a href="{{ $item['url'] }}" class="menu-link">
        <i class="menu-icon tf-icons bx {{ $item['icon'] }}"></i>
        <div>{{ $item['label'] }}</div>
      </a>
    </li>
  @endif
@endforeach
