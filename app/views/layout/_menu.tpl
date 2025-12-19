<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-start">
            <a class="navbar-brand nav-link{{ $uri == '/' ? ' active-link' : '' }}" href="/"
               {{ $uri == '/' ? 'aria-current="page"' : '' }}>
                {% if str_starts_with($uri, '/gestion') %}
                Retour au site
                {% else %}
                Accueil
                {% endif %}
            </a>

            {% foreach $menu_items as $item %}
            {% if !empty($item['pinned_on_mobile']) %}
            <a class="nav-link d-lg-none{{ $item['isActive'] ? ' ' : '' }}"
               href="{{ $item['url'] }}"
               {{ $item['isActive'] ? 'aria-current="page"' : '' }}>
                {{ $item['label'] }}
            </a>
            {% endif %}
            {% endforeach %}
        </div>

        <button class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarNav"
                aria-controls="navbarNav"
                aria-expanded="false"
                aria-label="Ouvrir le menu">
            <span class="navbar-toggler-icon" aria-hidden="true"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                {% foreach $menu_items as $item %}
                {% if $item['type'] == 'link' %}
                <li class="nav-item">
                    <a class="nav-link{{ $item['isActive'] ? ' active-link' : '' }}{{ !empty($item['pinned_on_mobile']) ? ' d-none d-lg-block' : '' }}"
                       href="{{ $item['url'] }}"
                       {{ $item['isActive'] ? 'aria-current="page"' : '' }}>
                        {{ $item['label'] }}
                    </a>
                </li>
                {% elseif $item['type'] == 'dropdown' %}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle{{ $item['isActive'] ? ' active-link' : '' }}"
                       href="#"
                       id="menuDropdown_{{ $item['label'] }}"
                       role="button"
                       data-bs-toggle="dropdown"
                       aria-expanded="false">
                        {{ $item['label'] }}
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="menuDropdown_{{ $item['label'] }}">
                        {% foreach $item['children'] as $child %}
                        <li>
                            <a class="dropdown-item{{ $child['isActive'] ? ' active-link' : '' }}"
                               href="{{ $child['url'] }}"
                               {{ $child['isActive'] ? 'aria-current="page"' : '' }}>
                                {{ $child['label'] }}
                            </a>
                        </li>
                        {% endforeach %}
                    </ul>
                </li>
                {% endif %}
                {% endforeach %}
            </ul>
        </div>
    </div>
</nav>
