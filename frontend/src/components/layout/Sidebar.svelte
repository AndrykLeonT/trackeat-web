<script>
    import { page, Link } from '@inertiajs/svelte';
    import logo from '../../assets/images/logo.png';
    import LayoutDashboard from '@lucide/svelte/icons/layout-dashboard';
    import CookingPot from '@lucide/svelte/icons/cooking-pot';
    import Utensils from '@lucide/svelte/icons/utensils';
    import BookOpen from '@lucide/svelte/icons/book-open';
    import ChefHat from '@lucide/svelte/icons/chef-hat';
    import LogOut from '@lucide/svelte/icons/log-out';
    import ExternalLink from '@lucide/svelte/icons/external-link';

    let { onNavigate = null } = $props();

    // Secciones organizadas de los módulos de TrackEat
    const navigationSections = [
        {
            title: 'Operación',
            items: [
                {
                    name: 'Dashboard',
                    href: '/dashboard',
                    icon: LayoutDashboard,
                },
                {
                    name: 'Comandas',
                    href: '/comandas',
                    icon: CookingPot,
                },
            ],
        },
        {
            title: 'Carta y Cocina',
            items: [
                {
                    name: 'Menú Digital',
                    href: '/menu',
                    icon: Utensils,
                },
                {
                    name: 'Recetas',
                    href: '/recetas',
                    icon: BookOpen,
                },
            ],
        },
    ];

    function isItemActive(href) {
        const currentUrl = $page?.url || '';
        if (href === '/dashboard') {
            return currentUrl === '/dashboard' || currentUrl === '/';
        }
        return currentUrl.startsWith(href);
    }

    function handleNavClick() {
        if (typeof onNavigate === 'function') {
            onNavigate();
        }
    }
</script>

<nav class="flex h-full flex-col justify-between p-4 bg-white text-gray-800 selection:bg-orange-100 select-none">
    <!-- Parte superior: Logo y Navegación -->
    <div class="flex flex-1 flex-col overflow-y-auto pr-1 -mr-1">
        <!-- Encabezado con Logo grande -->
        <div class="mb-6 px-2 pt-2 pb-4 border-b border-gray-100">
            <Link
                href="/dashboard"
                onclick={handleNavClick}
                class="inline-block transition-transform duration-200 hover:scale-[1.02]"
            >
                <img src={logo} alt="TrackEat" class="h-11 w-auto object-contain" />
            </Link>
        </div>

        <!-- Lista de Módulos agrupados por sección -->
        <div class="space-y-6">
            {#each navigationSections as section}
                <div>
                    <p class="px-3 mb-2 text-[11px] font-bold uppercase tracking-wider text-gray-400">
                        {section.title}
                    </p>
                    <ul class="space-y-1.5">
                        {#each section.items as item}
                            {@const active = isItemActive(item.href)}
                            {@const Icon = item.icon}
                            <li>
                                <Link
                                    href={item.href}
                                    onclick={handleNavClick}
                                    class="group relative flex items-center justify-between gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all duration-200 {active
                                        ? 'bg-orange-50 text-orange-600 font-semibold shadow-xs ring-1 ring-orange-200/60'
                                        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'}"
                                >
                                    <div class="flex items-center gap-3 min-w-0">
                                        <Icon
                                            class="size-5 shrink-0 transition-transform duration-200 {active
                                                ? 'text-orange-600 scale-105'
                                                : 'text-gray-400 group-hover:text-gray-700 group-hover:scale-105'}"
                                        />
                                        <span class="truncate">{item.name}</span>
                                    </div>

                                    {#if active}
                                        <span class="h-1.5 w-1.5 rounded-full bg-orange-600"></span>
                                    {/if}
                                </Link>
                            </li>
                        {/each}
                    </ul>
                </div>
            {/each}
        </div>
    </div>

    <!-- Parte inferior: Perfil de Usuario y Enlace a Landing Pública -->
    <div class="border-t border-gray-100 pt-3 mt-4 space-y-2">
        <!-- Acceso rápido a la landing pública -->
        <a
            href="/"
            onclick={handleNavClick}
            class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors"
        >
            <span class="flex items-center gap-2">
                <ExternalLink class="size-3.5 text-gray-400" />
                <span>Ver sitio público</span>
            </span>
            <span class="text-[10px] text-gray-400 uppercase font-semibold">Landing</span>
        </a>

        <!-- Tarjeta de Usuario en Turno -->
        <div class="flex items-center justify-between rounded-xl bg-gray-50/90 p-2.5 border border-gray-200/70">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-orange-500 to-amber-600 text-white shadow-xs">
                    {#if $page?.props?.auth?.user?.name}
                        <span class="text-xs font-bold uppercase">
                            {$page.props.auth.user.name.slice(0, 2)}
                        </span>
                    {:else}
                        <ChefHat class="size-4.5" />
                    {/if}
                </div>
                <div class="flex flex-col min-w-0">
                    <span class="truncate text-xs font-semibold text-gray-800">
                        {$page?.props?.auth?.user?.name ?? 'Chef de Cocina'}
                    </span>
                    <span class="truncate text-[10px] text-gray-500">
                        {$page?.props?.auth?.user?.email ?? 'Turno Activo'}
                    </span>
                </div>
            </div>

            {#if $page?.props?.auth?.user}
                <Link
                    href={typeof route === 'function' ? route('logout') : '/logout'}
                    method="post"
                    as="button"
                    title="Cerrar sesión"
                    class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-200/60 hover:text-gray-700 transition-colors"
                >
                    <LogOut class="size-4" />
                    <span class="sr-only">Cerrar sesión</span>
                </Link>
            {/if}
        </div>
    </div>
</nav>