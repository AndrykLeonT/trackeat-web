<script>
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import MenuCard from '@/components/ui/menu-card.svelte';
    import Utensils from '@lucide/svelte/icons/utensils';
    import Plus from '@lucide/svelte/icons/plus';

    // =========================================================================
    // ARREGLO DE DATOS DE PLATILLOS
    // =========================================================================
    const dishes = [
        {
            id: 1,
            name: 'Tacos al Pastor Especiales',
            category: 'Tacos & Especialidades',
            price: 95.0,
            prepTime: '10-12 min',
            image: 'https://images.unsplash.com/photo-1551504734-5ee1c4a1479b?auto=format&fit=crop&w=700&q=80',
            summary: 'Tres tacos con carne de cerdo marinada al pastor, piña asada, cebolla morada y cilantro fresco.',
            description:
                'Nuestra receta insignia marinada con achiote y especias tradicionales por 24 horas, servida en tortillas de maíz recién hechas con piña asada al carbón, cebolla morada y cilantro criollo.',
            ingredients: [
                'Cerdo marinado',
                'Achiote tradicional',
                'Piña asada',
                'Cilantro fresco',
                'Cebolla morada',
                'Tortilla de maíz',
            ],
            isPopular: true,
            tag: 'Favorito',
        },
        {
            id: 2,
            name: 'Hamburguesa Artesanal TrackEat',
            category: 'Hamburguesas',
            price: 145.0,
            prepTime: '12-15 min',
            image: 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=700&q=80',
            summary: '180g de carne de res selecta, queso cheddar fundido, tocino crujiente y aderezo especial en pan brioche.',
            description:
                'Carne de res 100% Angus cocinada al término ideal sobre plancha caliente, cubierta con queso cheddar añejo fundido, tiras de tocino ahumado, lechuga romana, tomate y nuestra salsa secreta de la casa.',
            ingredients: [
                'Carne Angus 180g',
                'Queso Cheddar',
                'Tocino ahumado',
                'Pan Brioche',
                'Salsa de la casa',
                'Cebolla caramelizada',
            ],
            isPopular: true,
            tag: 'Recomendado',
        },
        {
            id: 3,
            name: 'Burrito Norteño de Asada',
            category: 'Tacos & Especialidades',
            price: 120.0,
            prepTime: '10-14 min',
            image: 'https://images.unsplash.com/photo-1626700051175-6818013e1d4f?auto=format&fit=crop&w=700&q=80',
            summary: 'Tortilla gigante de harina rellena de carne asada al carbón, frijoles refritos, queso y guacamole.',
            description:
                'Gran tortilla de trigo artesanal sellada a la plancha, rellena de abundante carne asada al carbón, frijoles bayos refritos, queso asadero gratinado, pico de gallo fresco y cremoso guacamole recién preparado.',
            ingredients: [
                'Carne asada al carbón',
                'Tortilla de harina XL',
                'Frijoles refritos',
                'Guacamole fresco',
                'Queso asadero',
                'Pico de gallo',
            ],
            isPopular: false,
            tag: 'Generoso',
        },
        {
            id: 4,
            name: 'Smash Burger Doble Queso',
            category: 'Hamburguesas',
            price: 135.0,
            prepTime: '8-10 min',
            image: 'https://images.unsplash.com/photo-1586190848861-99aa4a171e90?auto=format&fit=crop&w=700&q=80',
            summary: 'Dos costras crujientes de carne smash con doble queso americano, pepinillos y mostaza dulce.',
            description:
                'Técnica smash clásica que crea una costra caramelizada inigualable. Lleva dos medallones de res, doble capa de queso americano derretido, pepinillos agridulces y cebolla picada finamente.',
            ingredients: [
                'Doble carne smash',
                'Doble queso americano',
                'Pepinillos encurtidos',
                'Cebolla blanca',
                'Pan de papa artesanal',
            ],
            isPopular: false,
            tag: 'Nuevo',
        },
        {
            id: 5,
            name: 'Papas Rústicas Trufadas',
            category: 'Acompañamientos',
            price: 70.0,
            prepTime: '6-8 min',
            image: 'https://images.unsplash.com/photo-1576107232684-1279f3908594?auto=format&fit=crop&w=700&q=80',
            summary: 'Papas con corte artesanal sazonadas con sal marina, romero fresco y aceite de trufa blanca.',
            description:
                'Papas naturales fritas en dos tiempos para lograr un exterior extra crujiente y corazón suave, infusionadas con aceite de trufa blanca, queso parmesano recién rallado y romero del huerto.',
            ingredients: [
                'Papas naturales',
                'Aceite de trufa blanca',
                'Queso Parmesano',
                'Romero fresco',
                'Sal marina gruesa',
            ],
            isPopular: false,
            tag: 'Crujiente',
        },
        {
            id: 6,
            name: 'Agua Fresca de Horchata Artesanal',
            category: 'Bebidas',
            price: 40.0,
            prepTime: '2-3 min',
            image: 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?auto=format&fit=crop&w=700&q=80',
            summary: 'Bebida tradicional mexicana de arroz y canela con toque de vainilla y leche condensada.',
            description:
                'Receta de la casa elaborada con arroz seleccionado a fuego lento, canela entera molida en metate, vainilla natural y leche condensada, servida con hielo frappé.',
            ingredients: [
                'Arroz seleccionado',
                'Canela en rama',
                'Leche condensada',
                'Extracto de vainilla',
                'Hielo frappé',
            ],
            isPopular: true,
            tag: 'Tradicional',
        },
        {
            id: 7,
            name: 'Limonada Mineral con Hierbabuena',
            category: 'Bebidas',
            price: 45.0,
            prepTime: '2-3 min',
            image: 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?auto=format&fit=crop&w=700&q=80',
            summary: 'Jugo de limón recién exprimido con agua mineral de manantial y hojas frescas de hierbabuena.',
            description:
                'Bebida intensamente refrescante con limones de la región exprimidos al momento, agua mineral con burbuja fina, azúcar de caña orgánica y hierbabuena fresca macerada suavemente.',
            ingredients: [
                'Limón criollo fresco',
                'Agua mineral con gas',
                'Hierbabuena fresca',
                'Jarabe artesanal',
                'Rodaja de lima',
            ],
            isPopular: false,
            tag: 'Refrescante',
        },
        {
            id: 8,
            name: 'Churros Clásicos con Cajeta',
            category: 'Postres',
            price: 65.0,
            prepTime: '7-9 min',
            image: 'https://images.unsplash.com/photo-1624353365286-3f8d62daad51?auto=format&fit=crop&w=700&q=80',
            summary: 'Cuatro churros recién hechos espolvoreados con azúcar y canela, acompañados de cajeta quemada.',
            description:
                'Churros hechos al momento con masa ligera dorada a la perfección, pasados por azúcar glass con canela molida, servidos con dos copitas de cajeta quemada de Celaya y chocolate caliente.',
            ingredients: [
                'Masa artesanal',
                'Canela fina',
                'Azúcar morena',
                'Cajeta de Celaya',
                'Salsa de chocolate',
            ],
            isPopular: true,
            tag: 'Dulce',
        },
    ];
</script>

<PageTitle title="Menú Digital — TrackEat" />

<AppLayout>
    <div class="space-y-6">
        <!-- ==================== ENCABEZADO DE PÁGINA ==================== -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-gray-200/80 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-orange-100 text-orange-600">
                        <Utensils class="size-4" />
                    </span>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Menú Digital</h1>
                </div>
                <p class="mt-1 text-sm text-gray-500">
                    Catálogo de platillos, recetas e ingredientes del restaurante.
                </p>
            </div>

            <!-- Botón Nuevo Platillo -->
            <div>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-orange-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-orange-700 transition-colors"
                >
                    <Plus class="size-4" />
                    <span>Nuevo Platillo</span>
                </button>
            </div>
        </div>

        <!-- ==================== GRID DE TARJETAS ==================== -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {#each dishes as dish (dish.id)}
                <MenuCard {dish} />
            {/each}
        </div>
    </div>
</AppLayout>
