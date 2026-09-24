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
            class="card-face card-front absolute inset-0 flex flex-col overflow-hidden rounded-2xl border border-gray-200 shadow-xs transition-shadow hover:shadow-md"
        >
            <!-- Imagen del Platillo con badges -->
            <div class="relative h-48 w-full shrink-0 overflow-hidden bg-gray-100">
                <img
                    src={dish.image}
                    alt={dish.name}
                    loading="lazy"
                    class="h-full w-full object-cover transition-transform duration-500 hover:scale-105"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-black/20"></div>

                <!-- Badges superiores -->
                <div class="absolute top-3 inset-x-3 flex items-center justify-between gap-2">
                    <span class="rounded-full bg-white/95 px-2.5 py-1 text-[11px] font-bold text-gray-800 shadow-xs">
                        {dish.category}
                    </span>

                    {#if dish.isPopular}
                        <span class="inline-flex items-center gap-1 rounded-full bg-orange-600 px-2.5 py-1 text-[11px] font-bold text-white shadow-xs">
                            <Sparkles class="size-3" />
                            Popular
                        </span>
                    {:else if dish.tag}
                        <span class="rounded-full bg-amber-500 px-2.5 py-1 text-[11px] font-bold text-white shadow-xs">
                            {dish.tag}
                        </span>
                    {/if}
                </div>

                <!-- Precio sobre la imagen -->
                <div class="absolute bottom-2.5 right-3">
                    <span class="rounded-xl bg-gray-900/85 px-2.5 py-1 text-xs font-black text-white shadow-xs">
                        ${dish.price.toFixed(2)} MXN
                    </span>
                </div>
            </div>

            <!-- Información Principal -->
            <div class="flex flex-1 flex-col justify-between p-4 bg-white">
                <div>
                    <h3 class="text-base font-bold text-gray-900 line-clamp-1">{dish.name}</h3>
                    <p class="mt-1.5 text-xs text-gray-500 line-clamp-2 leading-relaxed">
                        {dish.summary}
                    </p>
                </div>

                <!-- Meta: Tiempo de preparación y estado de cocina -->
                <div class="flex items-center justify-between text-xs text-gray-500 pt-2 border-t border-gray-100">
                    <span class="inline-flex items-center gap-1 font-medium text-gray-600">
                        <Clock class="size-3.5 text-orange-500" />
                        {dish.prepTime}
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200/50">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Cocina lista
                    </span>
                </div>
            </div>
        </article>

        <!-- ==================== CARA POSTERIOR (RECETA Y DETALLES) ==================== -->
        <article
            class="card-face card-back absolute inset-0 flex flex-col justify-between overflow-hidden rounded-2xl border border-gray-200 p-5 shadow-md"
        >
            <div class="flex-1 overflow-y-auto pr-1">
                <!-- Encabezado de la Cara Posterior -->
                <div class="flex items-start justify-between gap-2 border-b border-gray-100 pb-3">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-orange-600">
                            {dish.category}
                        </span>
                        <h4 class="text-base font-bold text-gray-900 leading-tight">
                            {dish.name}
                        </h4>
                    </div>
                    <span class="text-lg font-black text-orange-600 shrink-0">
                        ${dish.price.toFixed(2)}
                    </span>
                </div>

                <!-- Descripción culinaria completa -->
                <p class="mt-3 text-xs leading-relaxed text-gray-600">
                    {dish.description}
                </p>

                <!-- Lista de Ingredientes -->
                <div class="mt-3.5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">
                        Ingredientes principales:
                    </span>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        {#each dish.ingredients as ingredient}
                            <span class="rounded-lg bg-orange-50 border border-orange-200/60 px-2 py-0.5 text-[11px] font-medium text-orange-800">
                                {ingredient}
                            </span>
                        {/each}
                    </div>
                </div>

                <!-- Tiempo KDS & Disponibilidad -->
                <div class="mt-4 flex items-center justify-between rounded-xl bg-gray-50 border border-gray-100 p-2.5 text-xs">
                    <span class="text-gray-600 font-medium">
                        Tiempo estimado: <strong class="text-gray-900">{dish.prepTime}</strong>
                    </span>
                    <span class="font-semibold text-emerald-600">
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
        background-color: #ffffff;
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
        .card-flipper {
            transition: none;
        }
    }
</style>
