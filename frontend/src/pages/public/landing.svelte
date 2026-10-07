<script>
    import PageTitle from '@/components/page-title.svelte';
    import imgLogo from '../../assets/images/logo.png';
    import imgChef from '../../assets/images/chef.jpg';
    import imgAngie from '../../assets/images/angie.jpg';
    import imgAndryk from '../../assets/images/andryk.jpg';
    import imgOmar from '../../assets/images/omar.jpg';
    import imgDaniel from '../../assets/images/daniel.jpg';
    import Menu from '@lucide/svelte/icons/menu';
    import X from '@lucide/svelte/icons/x';

    let isLandingMenuOpen = $state(false);

    // Añade la clase que dispara la animación de entrada la primera vez
    // que la sección entra al viewport, y luego deja de observar.
    function reveal(node) {
        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    node.classList.add('anim-entrada-activa');
                    observer.disconnect();
                }
            },
            { threshold: 0.2 },
        );

        observer.observe(node);

        return {
            destroy() {
                observer.disconnect();
            },
        };
    }

    const focoEncabezado =
        'outline-none rounded-md transition-transform duration-200 focus-visible:scale-[1.02] focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 motion-reduce:transition-none motion-reduce:focus-visible:scale-100';
</script>

<PageTitle title="TrackEat" />

<header class="sticky top-0 z-30 border-b border-border bg-background/95 backdrop-blur-md">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-4 sm:px-6 py-3.5">
        <a href="/" class="flex items-center">
            <img src={imgLogo} alt="TrackEat" class="h-8 w-auto object-contain" />
        </a>

        <!-- Navegación Desktop -->
        <nav aria-label="Secciones de la página" class="hidden md:flex items-center gap-8">
            <ul class="flex items-center gap-6 text-sm text-muted-foreground">
                <li><a href="#que-es" class="hover:text-foreground transition-colors">Qué es TrackEat</a></li>
                <li><a href="#por-que-usar" class="hover:text-foreground transition-colors">Por qué usar TrackEat</a></li>
                <li><a href="#nosotros" class="hover:text-foreground transition-colors">Acerca de nosotros</a></li>
            </ul>
            <a
                href="/dashboard"
                class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground hover:bg-primary/90 transition-colors shadow-xs"
            >
                Ir al Dashboard
            </a>
        </nav>

        <!-- Botones en Móvil -->
        <div class="flex items-center gap-2 md:hidden">
            <a
                href="/dashboard"
                class="rounded-lg bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/20 transition-colors"
            >
                Dashboard
            </a>
            <button
                type="button"
                onclick={() => (isLandingMenuOpen = !isLandingMenuOpen)}
                aria-label="Abrir menú"
                aria-expanded={isLandingMenuOpen}
                class="flex h-9 w-9 items-center justify-center rounded-lg text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
            >
                {#if isLandingMenuOpen}
                    <X class="size-5" />
                {:else}
                    <Menu class="size-5" />
                {/if}
            </button>
        </div>
    </div>

    <!-- Menú Desplegable Móvil -->
    {#if isLandingMenuOpen}
        <div class="border-t border-border bg-card px-4 py-4 md:hidden shadow-lg animate-in slide-in-from-top-2 duration-150">
            <nav aria-label="Navegación móvil">
                <ul class="flex flex-col space-y-2 text-sm font-medium text-card-foreground">
                    <li>
                        <a
                            href="#que-es"
                            onclick={() => (isLandingMenuOpen = false)}
                            class="block rounded-lg px-3 py-2.5 hover:bg-accent hover:text-accent-foreground transition-colors"
                        >
                            Qué es TrackEat
                        </a>
                    </li>
                    <li>
                        <a
                            href="#por-que-usar"
                            onclick={() => (isLandingMenuOpen = false)}
                            class="block rounded-lg px-3 py-2.5 hover:bg-accent hover:text-accent-foreground transition-colors"
                        >
                            Por qué usar TrackEat
                        </a>
                    </li>
                    <li>
                        <a
                            href="#nosotros"
                            onclick={() => (isLandingMenuOpen = false)}
                            class="block rounded-lg px-3 py-2.5 hover:bg-accent hover:text-accent-foreground transition-colors"
                        >
                            Acerca de nosotros
                        </a>
                    </li>
                    <li class="pt-2 border-t border-border">
                        <a
                            href="/dashboard"
                            onclick={() => (isLandingMenuOpen = false)}
                            class="flex w-full items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 shadow-xs transition-colors"
                        >
                            Ingresar al Sistema
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    {/if}
</header>

<main>
    <!-- HERO — se anima solo, al cargar -->
    <section class="anim-entrada anim-entrada-activa bg-background">
        <div class="mx-auto grid max-w-6xl grid-cols-1 items-center gap-12 px-6 py-20 lg:grid-cols-2">
            <div>
                <p class="mb-4 inline-flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
                    Sistema de gestión y comandas
                </p>

                <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                <h1 tabindex="0" class="text-4xl font-bold leading-tight text-foreground {focoEncabezado}">
                    Pedidos y seguimiento en tiempo real para restaurantes y food trucks.
                </h1>

                <p class="mt-6 max-w-md leading-relaxed text-muted-foreground">
                    Optimiza la experiencia de tus comensales y el trabajo de tu equipo de cocina. Menú digital accesible, toma de comandas inmediata
                    y monitoreo de estados sin fricciones.
                </p>

                <div class="mt-8 flex items-center gap-4">
                    <a href="#que-es" class="rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                        Conocer más
                    </a>
                </div>

                <hr class="my-8 border-border" />

                <ul class="flex flex-wrap gap-x-8 gap-y-2 text-sm text-muted-foreground">
                    <li>Capacitación pausada del software</li>
                    <li>Puesta en marcha inmediata</li>
                </ul>
            </div>

            <figure class="rounded-2xl border border-border bg-card p-6">
                <div class="mb-4 flex items-center justify-between text-xs">
                    <span class="inline-flex items-center gap-2 font-semibold tracking-wide text-primary uppercase">
                        <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
                        Experiencia culinaria eficiente
                    </span>
                    <span class="font-semibold tracking-wide text-muted-foreground uppercase">Tiempo real</span>
                </div>

                <img src={imgChef} alt="Ilustración de un chef sosteniendo una charola con un platillo" class="mx-auto block h-64 w-64" />

                <figcaption class="mt-4 flex items-center justify-between text-xs text-muted-foreground">
                    <span>Servicio sincronizado</span>
                    <span>Cocina & Salón conectados</span>
                </figcaption>
            </figure>
        </div>
    </section>

    <!-- QUÉ ES TRACKEAT — se anima al hacer scroll hasta aquí -->
    <section id="que-es" use:reveal class="anim-entrada bg-muted/40">
        <div class="mx-auto max-w-6xl px-6 py-20">
            <p class="mb-4 inline-flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
                Sistema integral
            </p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
            <h2 tabindex="0" class="text-3xl font-bold text-foreground {focoEncabezado}">¿Qué es TrackEat?</h2>
            <p class="mt-4 max-w-2xl leading-relaxed text-muted-foreground">
                TrackEat es un sistema integral de pedidos y gestión para restaurantes y food trucks que conecta en tiempo real a comensales, meseros
                y equipo de cocina, eliminando esperas y optimizando cada paso de la orden.
            </p>

            <ul class="mt-12 grid grid-cols-1 gap-6 md:grid-cols-3">
                <li class="li-resaltar rounded-xl border border-border bg-card p-6">
                    <div class="mb-4 h-10 w-10 rounded-lg bg-primary/10"></div>
                    <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">01 / Menú digital</p>
                    <h3 class="mt-2 font-semibold text-card-foreground">Menú Digital Interactivo</h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Los clientes exploran la carta completa con fotos, descripciones claras y precios actualizados directamente desde cualquier
                        dispositivo móvil, sin necesidad de descargas pesadas.
                    </p>
                    <hr class="my-4 border-border" />
                    <p class="text-xs tracking-wide text-muted-foreground uppercase">Acceso vía QR o terminal</p>
                </li>

                <li class="li-resaltar rounded-xl border border-border bg-card p-6">
                    <div class="mb-4 h-10 w-10 rounded-lg bg-primary/10"></div>
                    <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">02 / Comandas</p>
                    <h3 class="mt-2 font-semibold text-card-foreground">Gestión Ágil de Comandas</h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Toma y despacho de órdenes instantáneo sin los habituales errores de comandeo manual. Las notas especiales y modificaciones
                        llegan sin alteraciones directo al personal de cocina.
                    </p>
                    <hr class="my-4 border-border" />
                    <p class="text-xs tracking-wide text-muted-foreground uppercase">Cero extravíos de pedidos</p>
                </li>

                <li class="li-resaltar rounded-xl border border-border bg-card p-6">
                    <div class="mb-4 h-10 w-10 rounded-lg bg-primary/10"></div>
                    <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">03 / Tiempo real</p>
                    <h3 class="mt-2 font-semibold text-card-foreground">Monitoreo en Tiempo Real</h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Visibilidad simultánea del estado del pedido: "Recibido", "En preparación" y "Listo para entrega". Tanto comensales como
                        administración conocen el ritmo exacto del servicio.
                    </p>
                    <hr class="my-4 border-border" />
                    <p class="text-xs tracking-wide text-muted-foreground uppercase">Sincronización en vivo</p>
                </li>
            </ul>
        </div>
    </section>

    <!-- POR QUÉ USAR TRACKEAT — se anima al hacer scroll hasta aquí -->
    <section id="por-que-usar" use:reveal class="anim-entrada bg-muted/40">
        <div class="mx-auto max-w-6xl px-6 py-20">
            <div class="rounded-2xl border border-border bg-card p-10 shadow-sm">
                <p class="mb-4 inline-flex items-center gap-2 text-xs font-semibold tracking-wide text-primary uppercase">
                    <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
                    Beneficios clave
                </p>
                <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                <h2 tabindex="0" class="text-2xl font-bold text-card-foreground {focoEncabezado}">¿Por qué usar TrackEat?</h2>
                <p class="mt-3 max-w-2xl leading-relaxed text-muted-foreground">
                    Una plataforma pensada para responder a las exigencias operativas diarias de negocios gastronómicos modernos.
                </p>

                <dl class="mt-10 grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div class="rounded-lg border border-border p-6">
                        <dt class="text-3xl font-bold text-primary">-35%</dt>
                        <dd class="mt-2 font-medium text-card-foreground">Optimización de tiempos en cocina</dd>
                        <dd class="mt-2 text-sm leading-relaxed text-muted-foreground">
                            Reducción tangible en los tiempos de espera y rotación optimizada durante picos de servicio con pantallas KDS
                            sincronizadas.
                        </dd>
                        <dd class="mt-3 text-xs tracking-wide text-muted-foreground uppercase">Métricas KDS integradas</dd>
                    </div>

                    <div class="rounded-lg border border-border p-6">
                        <dt class="text-3xl font-bold text-card-foreground">0%</dt>
                        <dd class="mt-2 font-medium text-card-foreground">Cero comisiones ocultas / control total</dd>
                        <dd class="mt-2 text-sm leading-relaxed text-muted-foreground">
                            Tarifa transparente y predecible sin porcentajes abusivos por comensal o ticket atendido. Tu ingreso es completamente
                            tuyo.
                        </dd>
                        <dd class="mt-3 text-xs tracking-wide text-muted-foreground uppercase">Retención íntegra de ingresos</dd>
                    </div>

                    <div class="rounded-lg border border-border p-6">
                        <dt class="text-3xl font-bold text-card-foreground">&lt;15m</dt>
                        <dd class="mt-2 font-medium text-card-foreground">Fácil adopción sin hardware costoso</dd>
                        <dd class="mt-2 text-sm leading-relaxed text-muted-foreground">
                            Funciona en tabletas, smartphones y computadoras estándar. Carga tu menú y comienza a recibir pedidos en minutos.
                        </dd>
                        <dd class="mt-3 text-xs tracking-wide text-muted-foreground uppercase">Plug & play sin dependencias</dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>

    <!-- ACERCA DE NOSOTROS — se anima al hacer scroll, tarjetas con flip -->
    <section id="nosotros" use:reveal class="anim-entrada bg-muted/40">
        <div class="mx-auto max-w-6xl px-6 py-20">
            <p class="mb-4 inline-flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
                Equipo de desarrollo
            </p>
            <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
            <h2 tabindex="0" class="text-3xl font-bold text-foreground {focoEncabezado}">Acerca de nosotros</h2>
            <p class="mt-4 max-w-2xl leading-relaxed text-muted-foreground">
                Somos estudiantes de Ingeniería en Sistemas Computacionales, apasionados por la tecnología y preparándonos para ser desarrolladores de
                software de alto impacto.
            </p>
            <p class="mt-2 max-w-2xl leading-relaxed text-muted-foreground">
                Creamos TrackEat como una solución práctica y moderna para el sector gastronómico, uniendo ingeniería de software, arquitectura en
                tiempo real y diseño centrado en el usuario para resolver problemas reales de restaurantes y food trucks.
            </p>

            <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div class="[perspective:1000px]">
                    <div
                        class="relative h-80 w-full [transform-style:preserve-3d] transition-transform duration-700 ease-out hover:[transform:rotateY(180deg)] motion-reduce:transition-none motion-reduce:hover:[transform:none]"
                    >
                        <article
                            class="absolute inset-0 flex flex-col rounded-xl border border-border bg-card p-6 text-center [backface-visibility:hidden]"
                        >
                            <div class="mx-auto mb-4 h-12 w-12 rounded-lg bg-primary/10"></div>
                            <h3 class="font-semibold text-card-foreground">Angélica Menchaca Rueda</h3>
                            <p class="mt-1 text-xs font-semibold tracking-wide text-primary uppercase">Desarrollador Frontend</p>
                            <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                                Enfocado en el diseño de interfaces limpias, accesibilidad web y la experiencia interactiva para clientes y
                                comensales.
                            </p>
                            <a href="mailto:L22310573@lapaz.tecnm.mx" class="mt-4 block text-sm text-primary hover:underline">
                                L22310573@lapaz.tecnm.mx
                            </a>
                        </article>
                        <div
                            class="absolute inset-0 overflow-hidden rounded-xl border border-border [backface-visibility:hidden] [transform:rotateY(180deg)]"
                        >
                            <img src={imgAngie} alt="Foto de Integrante 1" class="h-full w-full object-cover" />
                        </div>
                    </div>
                </div>

                <div class="[perspective:1000px]">
                    <div
                        class="relative h-80 w-full [transform-style:preserve-3d] transition-transform duration-700 ease-out hover:[transform:rotateY(180deg)] motion-reduce:transition-none motion-reduce:hover:[transform:none]"
                    >
                        <article
                            class="absolute inset-0 flex flex-col rounded-xl border border-border bg-card p-6 text-center [backface-visibility:hidden]"
                        >
                            <div class="mx-auto mb-4 h-12 w-12 rounded-lg bg-primary/10"></div>
                            <h3 class="font-semibold text-card-foreground">Andryk Manuel León Tapia</h3>
                            <p class="mt-1 text-xs font-semibold tracking-wide text-primary uppercase">Desarrollador Backend</p>
                            <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                                Especializado en la lógica de negocio, APIs en tiempo real y la sincronización confiable del flujo de comandas KDS.
                            </p>
                            <a href="mailto:L22310560@lapaz.tecnm.mx" class="mt-4 block text-sm text-primary hover:underline">
                                L22310560@lapaz.tecnm.mx
                            </a>
                        </article>
                        <div
                            class="absolute inset-0 overflow-hidden rounded-xl border border-border [backface-visibility:hidden] [transform:rotateY(180deg)]"
                        >
                            <img src={imgAndryk} alt="Foto de Integrante 2" class="h-full w-full object-cover" />
                        </div>
                    </div>
                </div>

                <div class="[perspective:1000px]">
                    <div
                        class="relative h-80 w-full [transform-style:preserve-3d] transition-transform duration-700 ease-out hover:[transform:rotateY(180deg)] motion-reduce:transition-none motion-reduce:hover:[transform:none]"
                    >
                        <article
                            class="absolute inset-0 flex flex-col rounded-xl border border-border bg-card p-6 text-center [backface-visibility:hidden]"
                        >
                            <div class="mx-auto mb-4 h-12 w-12 rounded-lg bg-primary/10"></div>
                            <h3 class="font-semibold text-card-foreground">Daniel Alexander Estrada Cosio</h3>
                            <p class="mt-1 text-xs font-semibold tracking-wide text-primary uppercase">Base de Datos & Cloud</p>
                            <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                                A cargo del modelado de datos, optimización de consultas concurrentes y la estabilidad de la infraestructura en la
                                nube.
                            </p>
                            <a href="mailto:L22310572@lapaz.tecnm.mx" class="mt-4 block text-sm text-primary hover:underline">
                                L22310572@lapaz.tecnm.mx
                            </a>
                        </article>
                        <div
                            class="absolute inset-0 overflow-hidden rounded-xl border border-border [backface-visibility:hidden] [transform:rotateY(180deg)]"
                        >
                            <img src={imgDaniel} alt="Foto de Integrante 3" class="h-full w-full object-cover" />
                        </div>
                    </div>
                </div>

                <div class="[perspective:1000px]">
                    <div
                        class="relative h-80 w-full [transform-style:preserve-3d] transition-transform duration-700 ease-out hover:[transform:rotateY(180deg)] motion-reduce:transition-none motion-reduce:hover:[transform:none]"
                    >
                        <article
                            class="absolute inset-0 flex flex-col rounded-xl border border-border bg-card p-6 text-center [backface-visibility:hidden]"
                        >
                            <div class="mx-auto mb-4 h-12 w-12 rounded-lg bg-primary/10"></div>
                            <h3 class="font-semibold text-card-foreground">Carlos Omar Celis Calzada</h3>
                            <p class="mt-1 text-xs font-semibold tracking-wide text-primary uppercase">QA & Arquitectura</p>
                            <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                                Garantizando la fiabilidad del software mediante pruebas continuas, control de calidad y validación de requerimientos.
                            </p>
                            <a href="mailto:L22310531@lapaz.tecnm.mx" class="mt-4 block text-sm text-primary hover:underline">
                                L22310531@lapaz.tecnm.mx
                            </a>
                        </article>
                        <div
                            class="absolute inset-0 overflow-hidden rounded-xl border border-border [backface-visibility:hidden] [transform:rotateY(180deg)]"
                        >
                            <img src={imgOmar} alt="Foto de Integrante 4" class="h-full w-full object-cover" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA FINAL — se anima al hacer scroll hasta aquí -->
    <section use:reveal class="anim-entrada bg-muted/40">
        <div class="mx-auto max-w-3xl px-6 py-20 text-center">
            <p class="mb-4 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Comienza hoy</p>
            <h2 class="text-3xl font-bold text-foreground">Eleva la sincronización de tu cocina y la satisfacción de tus clientes</h2>
            <p class="mt-4 leading-relaxed text-muted-foreground">
                Conoce TrackEat en acción y comunícate directamente con el equipo desarrollador para dudas, integración o demostraciones guiadas.
            </p>
            <div class="mt-8 flex items-center justify-center gap-4">
                <a href="#nosotros" class="rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                    Contactar al equipo
                </a>
                <a
                    href="#que-es"
                    class="rounded-md border border-border px-5 py-2.5 text-sm font-medium text-foreground hover:bg-accent hover:text-accent-foreground"
                >
                    Ver funcionalidades
                </a>
            </div>
        </div>
    </section>
</main>

<footer class="border-t border-border bg-card text-card-foreground">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 py-10 sm:py-12">
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
            <div>
                <img src={imgLogo} alt="TrackEat" class="h-6 w-auto" />
                <p class="mt-3 max-w-sm text-xs sm:text-sm leading-relaxed text-muted-foreground">
                    Plataforma integral de gestión y seguimiento de pedidos en tiempo real. Proyecto de software desarrollado por estudiantes de
                    Ingeniería en Sistemas Computacionales.
                </p>
            </div>

            <div>
                <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Contacto directo con los desarrolladores</p>
                <address class="mt-3 grid grid-cols-1 gap-2 text-xs sm:text-sm not-italic sm:grid-cols-2">
                    <p>
                        Angélica Menchaca — <a href="mailto:L22310573@lapaz.tecnm.mx" class="text-primary hover:underline">L22310573@lapaz.tecnm.mx</a
                        >
                    </p>
                    <p>
                        Andryk León — <a href="mailto:L22310560@lapaz.tecnm.mx" class="text-primary hover:underline">L22310560@lapaz.tecnm.mx</a>
                    </p>
                    <p>
                        Daniel Estrada — <a href="mailto:L22310572@lapaz.tecnm.mx" class="text-primary hover:underline">L22310572@lapaz.tecnm.mx</a>
                    </p>
                    <p>
                        Carlos Celis — <a href="mailto:L22310531@lapaz.tecnm.mx" class="text-primary hover:underline">L22310531@lapaz.tecnm.mx</a>
                    </p>
                </address>
            </div>
        </div>

        <hr class="my-6 sm:my-8 border-border" />

        <div class="flex flex-col items-center justify-between gap-2 text-xs text-muted-foreground sm:flex-row text-center sm:text-left">
            <p>© 2026 TrackEat. Desarrollado con dedicación para la optimización gastronómica.</p>
            <p class="uppercase tracking-wide text-[11px] text-muted-foreground">Ingeniería en Sistemas Computacionales</p>
        </div>
    </div>
</footer>

<style>
    @keyframes entrada {
        0% {
            opacity: 0;
            transform: translateY(24px);
        }
        30% {
            opacity: 0.4;
            transform: translateY(16px);
        }
        70% {
            opacity: 1;
            transform: translateY(-4px);
        }
        100% {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .anim-entrada {
        opacity: 0;
    }
    .anim-entrada-activa {
        animation: entrada 0.8s ease-out forwards;
    }

    @keyframes resaltar {
        0% {
            transform: translateY(0) scale(1);
        }
        30% {
            transform: translateY(-10px) scale(1.03);
        }
        60% {
            transform: translateY(-4px) scale(1.01);
        }
        100% {
            transform: translateY(-6px) scale(1.02);
        }
    }
    .li-resaltar:hover {
        animation: resaltar 0.45s ease-out forwards;
    }

    @media (prefers-reduced-motion: reduce) {
        .anim-entrada {
            opacity: 1;
        }
        .anim-entrada-activa {
            animation: none;
        }
        .li-resaltar:hover {
            animation: none;
        }
    }
</style>
