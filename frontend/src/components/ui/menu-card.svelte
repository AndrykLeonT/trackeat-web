<script>
    import Clock from '@lucide/svelte/icons/clock';
    import Sparkles from '@lucide/svelte/icons/sparkles';

    let { dish } = $props();

    let isFlipped = $state(false);

    function toggleFlip() {
        isFlipped = !isFlipped;
    }
</script>

<div class="card-container h-[380px] w-full select-none">
    <!-- Contenedor 3D con animación al hacer hover o clic -->
    <div
        class="card-flipper relative h-full w-full {isFlipped ? 'is-flipped' : ''}"
        onclick={toggleFlip}
        role="button"
        tabindex="0"
        onkeydown={(e) => (e.key === 'Enter' || e.key === ' ') && toggleFlip()}
        aria-label="Ver detalles de {dish.name}"
    >
        <!-- ==================== CARA FRONTAL (FOTO Y RESUMEN) ==================== -->
        <article
            class="card-face card-front absolute inset-0 flex flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-xs transition-shadow hover:shadow-md"
        >
            <!-- Imagen del Platillo con badges -->
            <div class="relative h-48 w-full shrink-0 overflow-hidden bg-muted">
                <picture>
                    <source
                        srcset="{dish.image} 400w, {dish.image} 800w, {dish.image} 1200w"
                        sizes="(max-width: 600px) 100vw, 600px"
                        type="image/webp"
                    />
                    <img
                        src="{dish.image}"
                        alt="{dish.name}"
                        loading="lazy"
                        class="h-full w-full object-cover transition-transform duration-500 hover:scale-105"
                    />
                </picture>
                <div class="absolute inset-0 bg-gradient-to-t from-background/60 via-transparent to-background/20"></div>

                <!-- Badges superiores -->
                <div class="absolute top-3 inset-x-3 flex items-center justify-between gap-2">
                    <span class="rounded-full bg-card/95 px-2.5 py-1 text-[11px] font-bold text-card-foreground shadow-xs border border-border/50">
                        {dish.category}
                    </span>

                    {#if dish.isPopular}
                        <span class="inline-flex items-center gap-1 rounded-full bg-primary px-2.5 py-1 text-[11px] font-bold text-primary-foreground shadow-xs">
                            <Sparkles class="size-3" />
                            Popular
                        </span>
                    {:else if dish.tag}
                        <span class="rounded-full bg-secondary px-2.5 py-1 text-[11px] font-bold text-secondary-foreground shadow-xs border border-border/40">
                            {dish.tag}
                        </span>
                    {/if}
                </div>

                <!-- Precio sobre la imagen -->
                <div class="absolute bottom-2.5 right-3">
                    <span class="rounded-xl bg-background/90 px-2.5 py-1 text-xs font-black text-foreground shadow-xs border border-border/40">
                        ${dish.price.toFixed(2)} MXN
                    </span>
                </div>
            </div>

            <!-- Información Principal -->
            <div class="flex flex-1 flex-col justify-between p-4 bg-card">
                <div>
                    <h3 class="text-base font-bold text-card-foreground line-clamp-1">{dish.name}</h3>
                    <p class="mt-1.5 text-xs text-muted-foreground line-clamp-2 leading-relaxed">
                        {dish.summary}
                    </p>
                </div>

                <!-- Meta: Tiempo de preparación y estado de cocina -->
                <div class="flex items-center justify-between text-xs text-muted-foreground pt-2 border-t border-border">
                    <span class="inline-flex items-center gap-1 font-medium text-card-foreground">
                        <Clock class="size-3.5 text-primary" />
                        {dish.prepTime}
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-chart-2 bg-chart-2/10 px-2 py-0.5 rounded-full border border-chart-2/30">
                        <span class="h-1.5 w-1.5 rounded-full bg-chart-2 animate-pulse"></span>
                        Cocina lista
                    </span>
                </div>
            </div>
        </article>

        <!-- ==================== CARA POSTERIOR (RECETA Y DETALLES) ==================== -->
        <article
            class="card-face card-back absolute inset-0 flex flex-col justify-between overflow-hidden rounded-2xl border border-border bg-card p-5 shadow-md"
        >
            <div class="flex-1 overflow-y-auto pr-1">
                <!-- Encabezado de la Cara Posterior -->
                <div class="flex items-start justify-between gap-2 border-b border-border pb-3">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-primary">
                            {dish.category}
                        </span>
                        <h4 class="text-base font-bold text-card-foreground leading-tight">
                            {dish.name}
                        </h4>
                    </div>
                    <span class="text-lg font-black text-primary shrink-0">
                        ${dish.price.toFixed(2)}
                    </span>
                </div>

                <!-- Descripción culinaria completa -->
                <p class="mt-3 text-xs leading-relaxed text-muted-foreground">
                    {dish.description}
                </p>

                <!-- Lista de Ingredientes -->
                <div class="mt-3.5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                        Ingredientes principales:
                    </span>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        {#each dish.ingredients as ingredient}
                            <span class="rounded-lg bg-primary/10 border border-primary/20 px-2 py-0.5 text-[11px] font-medium text-primary">
                                {ingredient}
                            </span>
                        {/each}
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between rounded-xl bg-muted/50 border border-border p-2.5 text-xs">
                    <span class="text-muted-foreground font-medium">
                        ⏱️ Tiempo estimado: <strong class="text-card-foreground">{dish.prepTime}</strong>
                    </span>
                    <span class="font-semibold text-chart-2">
                        En menú digital
                    </span>
                </div>
            </div>
        </article>
    </div>
</div>

<style>
    .card-container {
        perspective: 1000px;
        -webkit-perspective: 1000px;
    }

    .card-flipper {
        transform-style: preserve-3d;
        -webkit-transform-style: preserve-3d;
        transition: transform 0.65s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .card-face {
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
        background-color: var(--card);
        color: var(--card-foreground);
        transform-style: preserve-3d;
        -webkit-transform-style: preserve-3d;
    }

    .card-front {
        transform: rotateY(0deg) translateZ(1px);
        -webkit-transform: rotateY(0deg) translateZ(1px);
    }

    .card-back {
        transform: rotateY(180deg) translateZ(1px);
        -webkit-transform: rotateY(180deg) translateZ(1px);
    }

    .card-flipper:hover,
    .card-flipper.is-flipped {
        transform: rotateY(180deg);
        -webkit-transform: rotateY(180deg);
    }

    @media (prefers-reduced-motion: reduce) {
        /* Desactivar transición y rotación del flip 3D */
        .card-flipper {
            transition: none;
        }

        .card-flipper:hover,
        .card-flipper.is-flipped {
            transform: none;
            -webkit-transform: none;
        }

        /* Desactivar zoom de imagen al hacer hover */
        .card-front img {
            transition: none !important;
        }
        .card-front img:hover {
            transform: none !important;
        }

        /* Desactivar animación de pulso en el indicador */
        .card-front :global(.animate-pulse) {
            animation: none !important;
        }

        /* Reemplazar flip 3D por crossfade de opacidad */
        .card-container {
            perspective: none;
            -webkit-perspective: none;
        }

        .card-flipper,
        .card-face {
            transform-style: flat;
            -webkit-transform-style: flat;
        }

        .card-front {
            transform: none;
            -webkit-transform: none;
            opacity: 1;
            transition: opacity 0.01s;
        }

        .card-back {
            transform: none;
            -webkit-transform: none;
            opacity: 0;
            transition: opacity 0.01s;
        }

        .card-flipper:hover .card-front,
        .card-flipper.is-flipped .card-front {
            transform: none;
            -webkit-transform: none;
            opacity: 0;
        }

        .card-flipper:hover .card-back,
        .card-flipper.is-flipped .card-back {
            transform: none;
            -webkit-transform: none;
            opacity: 1;
        }
    }
</style>
