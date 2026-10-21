<script>
    import logo from '../../assets/images/logo.png';
    import Menu from '@lucide/svelte/icons/menu';
    import ChefHat from '@lucide/svelte/icons/chef-hat';
    import { page, Link } from '@inertiajs/svelte';

    let {
        onToggleMobileMenu = () => {},
        isMobileMenuOpen = false,
        nativeNavigation = false,
        title = 'Panel Principal',
        connectionLabel = 'Servicio Activo',
        connected = true,
    } = $props();
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
            class="flex md:hidden h-9 w-9 items-center justify-center rounded-xl text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors focus:outline-none focus:ring-2 focus:ring-ring"
        >
            <Menu class="size-5" />
        </button>

        <!-- Logo en Móvil (en Desktop se muestra prominentemente en la Sidebar) -->
        <div class="flex items-center md:hidden">
            {#if nativeNavigation}
                <a href="/dashboard" class="flex items-center"><img src={logo} alt="TrackEat" class="h-8 w-auto object-contain" /></a>
            {:else}
                <Link href="/dashboard" class="flex items-center"><img src={logo} alt="TrackEat" class="h-8 w-auto object-contain" /></Link>
            {/if}
        </div>

        <!-- Indicador de ubicación / contexto en Desktop -->
        <div class="hidden md:flex items-center gap-2 text-sm text-muted-foreground">
            <span class="font-medium text-muted-foreground/80">TrackEat</span>
            <span class="text-border">/</span>
            <span class="font-semibold text-foreground">{title}</span>
        </div>
    </div>

    <!-- Lado Derecho: Estado del sistema y Avatar de Usuario -->
    <div class="flex items-center gap-3">
        <!-- Estado discreto; el detalle queda disponible al pasar el cursor y para lectores de pantalla. -->
        <div
            role="status"
            aria-label={connectionLabel}
            title={connectionLabel}
            class="inline-flex size-5 shrink-0 items-center justify-center"
        >
            <span aria-hidden="true" class="size-1.5 rounded-full {connected ? 'bg-chart-2' : 'bg-muted-foreground/60'}"></span>
        </div>

        <!-- Avatar / Perfil de Usuario -->
        <div class="flex items-center gap-2">
            <div
                class="flex h-8 w-8 sm:h-9 sm:w-9 items-center justify-center rounded-xl bg-primary text-primary-foreground font-bold text-xs shadow-xs"
            >
                {#if $page?.props?.auth?.user?.name}
                    {$page.props.auth.user.name.slice(0, 2).toUpperCase()}
                {:else}
                    <ChefHat class="size-4.5" />
                {/if}
            </div>
            {#if $page?.props?.auth?.user?.name}
                <div class="hidden lg:flex flex-col text-left leading-tight">
                    <span class="text-xs font-semibold text-foreground max-w-[120px] truncate">
                        {$page.props.auth.user.name}
                    </span>
                    <span class="text-[10px] text-muted-foreground">Personal</span>
                </div>
            {/if}
        </div>
    </div>
</div>
