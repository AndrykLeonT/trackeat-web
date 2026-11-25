<script>
    import { page, Link } from '@inertiajs/svelte';
    import logo from '../../assets/images/logo.png';
    import LayoutDashboard from '@lucide/svelte/icons/layout-dashboard';
    import CookingPot from '@lucide/svelte/icons/cooking-pot';
    import Utensils from '@lucide/svelte/icons/utensils';
    import BookOpen from '@lucide/svelte/icons/book-open';
    import LogOut from '@lucide/svelte/icons/log-out';
    import ExternalLink from '@lucide/svelte/icons/external-link';
    import Users from '@lucide/svelte/icons/users';
    import UserRound from '@lucide/svelte/icons/user-round';
    import Package from '@lucide/svelte/icons/package';
    import Tags from '@lucide/svelte/icons/tags';
    import Banknote from '@lucide/svelte/icons/banknote';
    import Wallet from '@lucide/svelte/icons/wallet';
    import Lock from '@lucide/svelte/icons/lock';
    import Receipt from '@lucide/svelte/icons/receipt';
    import Eraser from '@lucide/svelte/icons/eraser';
    import ClipboardList from '@lucide/svelte/icons/clipboard-list';
    import HandPlatter from '@lucide/svelte/icons/hand-platter';
    import { inicioPorRol, rolesPersonal } from '@/lib/inicio';

    let { onNavigate = null, nativeNavigation = false, currentUrl = null, user = null, roleLabel = '', logoutToken = '' } = $props();
    const role = $derived(user?.rol);

    const signedInRoles = ['cliente', 'administrador', 'cocinero', 'mesero', 'cajero'];

    // Secciones organizadas de los módulos de TrackEat
    const navigationSections = [
        {
            title: 'Operación',
            items: [
                // Solo el personal (los cuatro roles); clientes y visitantes no lo ven ni entran.
                {
                    name: 'Dashboard',
                    href: '/dashboard',
                    icon: LayoutDashboard,
                    roles: rolesPersonal,
                },
                {
                    name: 'Comandas',
                    href: '/comandas',
                    icon: CookingPot,
                    roles: ['cocinero', 'administrador'],
                },
                // Módulo 2 · HU-04: historial por día. Lo ve cualquier cuenta con sesión (el personal también levanta pedidos).
                { name: 'Mis pedidos', href: '/mis-pedidos', icon: ClipboardList, roles: signedInRoles },
                // Módulo 2 · HU-09: pedidos por entregar, a nivel de pedido.
                { name: 'Por entregar', href: '/mesero/pedidos', icon: HandPlatter, roles: ['mesero', 'cajero', 'administrador'] },
                {
                    name: 'Personal',
                    href: '/personal/',
                    icon: Users,
                    roles: rolesPersonal,
                    offlineCatalog: true,
                },
            ],
        },
        {
            title: 'Carta y Cocina',
            items: [
                {
                    name: 'Menú Digital',
                    href: '/menu/',
                    icon: Utensils,
                    offlineCatalog: true,
                },
                {
                    name: 'Recetas',
                    href: '/recetas',
                    icon: BookOpen,
                    roles: rolesPersonal,
                },
            ],
        },
        // Módulo 3 · Pagos y caja: cada enlace indica qué roles lo ven.
        {
            title: 'Caja',
            items: [
                { name: 'Por cobrar', href: '/caja', icon: Banknote, roles: ['cajero'] },
                { name: 'Turno actual', href: '/caja/turno', icon: Wallet, roles: ['cajero'] },
                { name: 'Cerrar corte', href: '/caja/cerrar', icon: Lock, roles: ['cajero'] },
                { name: 'Cortes', href: '/caja/cortes', icon: Receipt, roles: ['cajero', 'administrador'] },
                { name: 'Purga', href: '/admin/purga', icon: Eraser, roles: ['administrador'] },
            ],
        },
        {
            title: 'Administración',
            items: [
                { name: 'Panel de administración', href: '/admin', icon: LayoutDashboard, roles: ['administrador'] },
                { name: 'Usuarios', href: '/admin/usuarios', icon: Users, roles: ['administrador'] },
                { name: 'Pedidos', href: '/admin/pedidos', icon: ClipboardList, roles: ['administrador'] },
                {
                    name: 'Productos',
                    href: '/admin/productos',
                    icon: Package,
                    roles: ['administrador'],
                },
                {
                    name: 'Categorías',
                    href: '/admin/categorias',
                    icon: Tags,
                    roles: ['administrador'],
                },
            ],
        },
        {
            title: 'Cuenta',
            items: [{ name: 'Mi perfil', href: '/perfil', icon: UserRound, roles: signedInRoles }],
        },
    ];

    // Los enlaces sin `roles` los ven todos (solo el Menú Digital, que es público); las secciones vacías no se muestran.
    const inicio = $derived(inicioPorRol(role));
    let visibleSections = $derived.by(() => {
        return navigationSections
            .map((section) => ({ ...section, items: section.items.filter((item) => !item.roles || item.roles.includes(role)) }))
            .filter((section) => section.items.length > 0);
    });

    function isItemActive(href) {
        const url = currentUrl ?? $page?.url ?? '';
        if (href === '/dashboard') {
            return url === '/dashboard' || url === '/';
        }
        if (href === '/admin') return url.split('?')[0] === href;
        if (href === '/caja') {
            return url === '/caja' || url.startsWith('/caja/pedidos') || url.startsWith('/caja/abrir');
        }
        return url.startsWith(href);
    }

    function handleNavClick() {
        if (typeof onNavigate === 'function') {
            onNavigate();
        }
    }
</script>

{#snippet navContent(item, active)}
    {@const Icon = item.icon}
    <div class="flex items-center gap-3 min-w-0">
        <Icon
            class="size-5 shrink-0 transition-transform duration-200 {active
                ? 'text-sidebar-accent-foreground scale-105'
                : 'text-sidebar-foreground/60 group-hover:text-sidebar-accent-foreground group-hover:scale-105'}"
        />
        <span class="truncate">{item.name}</span>
    </div>
    {#if active}<span class="h-1.5 w-1.5 rounded-full bg-sidebar-primary"></span>{/if}
{/snippet}

<nav
    aria-label="Módulos de TrackEat"
    class="flex h-full flex-col justify-between p-4 bg-sidebar text-sidebar-foreground selection:bg-sidebar-accent select-none"
>
    <!-- Parte superior: Logo y Navegación -->
    <div class="flex flex-1 flex-col overflow-y-auto pr-1 -mr-1">
        <!-- Encabezado con Logo grande -->
        <div class="mb-6 px-2 pt-2 pb-4 border-b border-sidebar-border">
            {#if nativeNavigation}
                <a href={inicio} onclick={handleNavClick} class="inline-block transition-transform duration-200 hover:scale-[1.02]">
                    <img src={logo} alt="TrackEat" class="h-11 w-auto object-contain" />
                </a>
            {:else}
                <a href={inicio} onclick={handleNavClick} class="inline-block transition-transform duration-200 hover:scale-[1.02]">
                    <img src={logo} alt="TrackEat" class="h-11 w-auto object-contain" />
                </a>
            {/if}
        </div>

        <!-- Lista de Módulos agrupados por sección -->
        <div class="space-y-6">
            {#each visibleSections as section (section.title)}
                <div>
                    <p class="px-3 mb-2 text-[11px] font-bold uppercase tracking-wider text-sidebar-foreground/60">
                        {section.title}
                    </p>
                    <ul class="space-y-1.5">
                        {#each section.items as item (item.href)}
                            {@const active = isItemActive(item.href)}
                            {@const navClass = `group relative flex items-center justify-between gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all duration-200 ${
                                active
                                    ? 'bg-sidebar-accent text-sidebar-accent-foreground font-semibold shadow-xs ring-1 ring-sidebar-border'
                                    : 'text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'
                            }`}
                            <li>
                                {#if nativeNavigation || item.offlineCatalog}
                                    <a href={item.href} onclick={handleNavClick} aria-current={active ? 'page' : undefined} class={navClass}>
                                        {@render navContent(item, active)}
                                    </a>
                                {:else}
                                    <Link href={item.href} onclick={handleNavClick} aria-current={active ? 'page' : undefined} class={navClass}>
                                        {@render navContent(item, active)}
                                    </Link>
                                {/if}
                            </li>
                        {/each}
                    </ul>
                </div>
            {/each}
        </div>
    </div>

    <!-- Parte inferior: Perfil de Usuario y Enlace a Landing Pública -->
    <div class="border-t border-sidebar-border pt-3 mt-4 space-y-2">
        <!-- Acceso rápido a la landing pública -->
        <a
            href="/"
            onclick={handleNavClick}
            class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground transition-colors"
        >
            <span class="flex items-center gap-2">
                <ExternalLink class="size-3.5 text-sidebar-foreground/60" />
                <span>Ver sitio público</span>
            </span>
            <span class="text-[10px] text-sidebar-foreground/60 uppercase font-semibold">Landing</span>
        </a>

        <!-- Tarjeta de Usuario en Turno -->
        <div class="flex items-center justify-between rounded-xl bg-sidebar-accent/50 p-2.5 border border-sidebar-border">
            <div class="flex items-center gap-2.5 min-w-0">
                <div
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground shadow-xs"
                >
                    {#if user?.name}
                        <span class="text-xs font-bold uppercase">
                            {user.name.slice(0, 2)}
                        </span>
                    {:else}
                        <UserRound class="size-4.5" aria-hidden="true" />
                    {/if}
                </div>
                <div class="flex flex-col min-w-0">
                    <span class="truncate text-xs font-semibold text-sidebar-foreground">
                        {user?.name ?? 'Visitante'}
                    </span>
                    {#if user}
                        <span class="truncate text-[10px] text-sidebar-foreground/70">{roleLabel}</span>
                    {/if}
                </div>
            </div>

            {#if user && nativeNavigation && logoutToken}
                <form action="/logout" method="post">
                    <input type="hidden" name="_token" value={logoutToken} />
                    <button
                        type="submit"
                        title="Cerrar sesión"
                        class="rounded-lg p-1.5 text-sidebar-foreground/60 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground transition-colors"
                    >
                        <LogOut class="size-4" />
                        <span class="sr-only">Cerrar sesión</span>
                    </button>
                </form>
            {:else if user && !nativeNavigation}
                <Link
                    href={typeof route === 'function' ? route('logout') : '/logout'}
                    method="post"
                    as="button"
                    title="Cerrar sesión"
                    class="rounded-lg p-1.5 text-sidebar-foreground/60 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground transition-colors"
                >
                    <LogOut class="size-4" />
                    <span class="sr-only">Cerrar sesión</span>
                </Link>
            {/if}
        </div>
    </div>
</nav>
