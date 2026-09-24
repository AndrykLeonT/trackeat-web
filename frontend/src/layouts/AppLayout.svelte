<script>
    import Sidebar from "@/components/layout/Sidebar.svelte";
    import Header from "@/components/layout/Header.svelte";
    import Footer from "@/components/layout/Footer.svelte";
    import X from "@lucide/svelte/icons/x";

    let { children } = $props();

    // Estado del menú desplegable móvil
    let isMobileMenuOpen = $state(false);

    function toggleMobileMenu() {
        isMobileMenuOpen = !isMobileMenuOpen;
    }

    function closeMobileMenu() {
        isMobileMenuOpen = false;
    }
</script>

<div class="flex h-screen w-screen overflow-hidden bg-gray-50 text-gray-800">
    <!-- Barra lateral fija en Desktop (oculta en pantallas menores a md) -->
    <aside class="hidden md:block w-64 flex-shrink-0 border-r border-gray-200 bg-white">
        <Sidebar />
    </aside>

    <!-- Menú Desplegable Móvil (Slide-over Drawer) -->
    {#if isMobileMenuOpen}
        <!-- Backdrop oscuro de fondo -->
        <div
            role="button"
            tabindex="0"
            aria-label="Cerrar menú de navegación"
            class="fixed inset-0 z-40 bg-gray-900/50 backdrop-blur-xs transition-opacity md:hidden"
            onclick={closeMobileMenu}
            onkeydown={(e) => e.key === 'Escape' && closeMobileMenu()}
        ></div>

        <!-- Cajón deslizante móvil -->
        <div
            class="fixed inset-y-0 left-0 z-50 flex w-72 max-w-[85vw] flex-col bg-white shadow-2xl transition-transform duration-300 md:hidden animate-in slide-in-from-left duration-200"
            role="dialog"
            aria-modal="true"
        >
            <!-- Botón de Cerrar en la esquina superior del menú desplegable -->
            <div class="absolute top-4 right-3 z-10">
                <button
                    type="button"
                    onclick={closeMobileMenu}
                    aria-label="Cerrar navegación"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-700 transition-colors"
                >
                    <X class="size-5" />
                </button>
            </div>

            <!-- Contenido del Sidebar reutilizado -->
            <Sidebar onNavigate={closeMobileMenu} />
        </div>
    {/if}

    <!-- Área principal con Header, Contenido y Footer -->
    <div class="flex flex-1 flex-col overflow-y-auto min-w-0">
        <header class="sticky top-0 z-10 border-b border-gray-200 bg-white/90 backdrop-blur-md">
            <Header onToggleMobileMenu={toggleMobileMenu} {isMobileMenuOpen} />
        </header>

        <main class="flex-1 p-4 sm:p-6">
            {#if children}
                {@render children()}
            {/if}
        </main>

        <footer class="border-t border-gray-200 bg-white px-4 sm:px-6 py-2.5 sm:py-3">
            <Footer />
        </footer>
    </div>
</div>