<script>
    import logo from '../../assets/images/logo.png';
    import Menu from '@lucide/svelte/icons/menu';
    import ChefHat from '@lucide/svelte/icons/chef-hat';
    import { page, Link } from '@inertiajs/svelte';

    let { onToggleMobileMenu = () => {}, isMobileMenuOpen = false } = $props();
</script>

<div class="flex h-14 sm:h-16 items-center justify-between px-4 sm:px-6">
    <!-- Lado Izquierdo: Botón Hamburguesa en Móvil + Logo en Móvil + Breadcrumb en Desktop -->
    <div class="flex items-center gap-3">
        <!-- Botón Hamburguesa para desplegar el menú en móvil -->
        <button
            type="button"
            onclick={onToggleMobileMenu}
            aria-label="Abrir menú de navegación"
            aria-expanded={isMobileMenuOpen}
            class="flex md:hidden h-9 w-9 items-center justify-center rounded-xl text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors focus:outline-none focus:ring-2 focus:ring-orange-500/30"
        >
            <Menu class="size-5" />
        </button>

        <!-- Logo en Móvil (en Desktop se muestra prominentemente en la Sidebar) -->
        <div class="flex items-center md:hidden">
            <Link href="/dashboard" class="flex items-center">
                <img src={logo} alt="TrackEat" class="h-8 w-auto object-contain" />
            </Link>
        </div>

        <!-- Indicador de ubicación / contexto en Desktop -->
        <div class="hidden md:flex items-center gap-2 text-sm text-gray-500">
            <span class="font-medium text-gray-400">TrackEat</span>
            <span class="text-gray-300">/</span>
            <span class="font-semibold text-gray-800">Panel Principal</span>
        </div>
    </div>

    <!-- Lado Derecho: Estado del sistema y Avatar de Usuario -->
    <div class="flex items-center gap-3">
        <!-- Indicador de Cocina en Línea / Servicio Activo (en pantallas >= sm) -->
        <div class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 border border-emerald-200/60 shadow-xs">
            <span class="relative flex h-2 w-2">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
            </span>
            <span>Servicio Activo</span>
        </div>

        <!-- Avatar / Perfil de Usuario -->
        <div class="flex items-center gap-2">
            <div class="flex h-8 w-8 sm:h-9 sm:w-9 items-center justify-center rounded-xl bg-gradient-to-br from-orange-500 to-amber-600 text-white font-bold text-xs shadow-xs">
                {#if $page?.props?.auth?.user?.name}
                    {$page.props.auth.user.name.slice(0, 2).toUpperCase()}
                {:else}
                    <ChefHat class="size-4.5" />
                {/if}
            </div>
            {#if $page?.props?.auth?.user?.name}
                <div class="hidden lg:flex flex-col text-left leading-tight">
                    <span class="text-xs font-semibold text-gray-800 max-w-[120px] truncate">
                        {$page.props.auth.user.name}
                    </span>
                    <span class="text-[10px] text-gray-400">Personal</span>
                </div>
            {/if}
        </div>
    </div>
</div>