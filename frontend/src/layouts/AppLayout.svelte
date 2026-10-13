<script>
    import { tick } from 'svelte';
    import Sidebar from '@/components/layout/Sidebar.svelte';
    import Header from '@/components/layout/Header.svelte';
    import Footer from '@/components/layout/Footer.svelte';
    import X from '@lucide/svelte/icons/x';

    let {
        children,
        nativeNavigation = false,
        currentUrl = null,
        headerTitle = 'Panel Principal',
        connectionLabel = 'Servicio Activo',
        connected = true,
        contentId = undefined,
    } = $props();

    // Estado del menú desplegable móvil
    let isMobileMenuOpen = $state(false);
    let mobileMenuDialog = $state(null);
    let closeMenuButton = $state(null);

    async function toggleMobileMenu() {
        if (isMobileMenuOpen) {
            closeMobileMenu();
            return;
        }
        isMobileMenuOpen = true;
        await tick();
        mobileMenuDialog.showModal();
        closeMenuButton.focus();
    }

    function closeMobileMenu() {
        mobileMenuDialog?.close();
        isMobileMenuOpen = false;
    }
</script>

<div class="flex h-screen w-screen overflow-hidden bg-background text-foreground">
    <!-- Barra lateral fija en Desktop (oculta en pantallas menores a md) -->
    <aside class="hidden md:block w-64 flex-shrink-0 border-r border-sidebar-border bg-sidebar">
        <Sidebar {nativeNavigation} {currentUrl} />
    </aside>

    <!-- Menú Desplegable Móvil (Slide-over Drawer) -->
    {#if isMobileMenuOpen}
        <dialog
            bind:this={mobileMenuDialog}
            aria-label="Menú de navegación"
            onclose={() => {
                isMobileMenuOpen = false;
            }}
            class="fixed inset-0 z-50 m-0 h-dvh w-screen max-h-none max-w-none border-0 bg-transparent p-0 text-sidebar-foreground md:hidden"
        >
            <!-- Backdrop oscuro de fondo -->
            <button
                type="button"
                tabindex="-1"
                aria-label="Cerrar menú de navegación"
                class="fixed inset-0 z-40 bg-foreground/50 backdrop-blur-xs transition-opacity md:hidden"
                onclick={closeMobileMenu}
            ></button>

            <!-- Cajón deslizante móvil -->
            <div
                class="absolute inset-y-0 left-0 z-50 flex w-72 max-w-[85vw] flex-col bg-sidebar shadow-2xl border-r border-sidebar-border transition-transform duration-300 md:hidden animate-in slide-in-from-left duration-200"
            >
                <!-- Botón de Cerrar en la esquina superior del menú desplegable -->
                <div class="absolute top-4 right-3 z-10">
                    <button
                        bind:this={closeMenuButton}
                        type="button"
                        onclick={closeMobileMenu}
                        aria-label="Cerrar navegación"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-sidebar-foreground/60 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground transition-colors"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <!-- Contenido del Sidebar reutilizado -->
                <Sidebar onNavigate={closeMobileMenu} {nativeNavigation} {currentUrl} />
            </div>
        </dialog>
    {/if}

    <!-- Área principal con Header, Contenido y Footer -->
    <div class="flex flex-1 flex-col overflow-y-auto min-w-0">
        <header class="sticky top-0 z-10 border-b border-border bg-card/90 backdrop-blur-md">
            <Header onToggleMobileMenu={toggleMobileMenu} {isMobileMenuOpen} {nativeNavigation} title={headerTitle} {connectionLabel} {connected} />
        </header>

        <main id={contentId} tabindex="-1" class="flex-1 p-4 sm:p-6">
            {#if children}
                {@render children()}
            {/if}
        </main>

        <footer class="border-t border-border bg-card px-4 sm:px-6 py-2.5 sm:py-3">
            <Footer />
        </footer>
    </div>
</div>
