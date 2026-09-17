import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import imgLogo from "../../assets/images/logo.png"
import imgChef from "../../assets/images/chef.jpg"
import imgAngie from "../../assets/images/angie.jpg"
import imgAndryk from "../../assets/images/andryk.jpg"
import imgOmar from "../../assets/images/omar.jpg"
import imgDaniel from "../../assets/images/daniel.jpg"

function useReveal<T extends HTMLElement>() {
    const ref = useRef<T | null>(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setVisible(true);
                    observer.disconnect();
                }
            },
            { threshold: 0.2 },
        );

        observer.observe(el);
        return () => observer.disconnect();
    }, []);

    return { ref, visible };
}

export default function Landing() {
    const queEs = useReveal<HTMLElement>();
    const porQueUsar = useReveal<HTMLElement>();
    const nosotros = useReveal<HTMLElement>();
    const cta = useReveal<HTMLElement>();

    const focoEncabezado =
        'outline-none rounded-md transition-transform duration-200 focus-visible:scale-[1.02] focus-visible:ring-2 focus-visible:ring-orange-400 focus-visible:ring-offset-2 motion-reduce:transition-none motion-reduce:focus-visible:scale-100';

    return (
        <>
            <Head title="TrackEat" />

            <style>{`
                @keyframes entrada {
                    0%   { opacity: 0; transform: translateY(24px); }
                    30%  { opacity: 0.4; transform: translateY(16px); }
                    70%  { opacity: 1; transform: translateY(-4px); }
                    100% { opacity: 1; transform: translateY(0); }
                }
                .anim-entrada { opacity: 0; }
                .anim-entrada-activa { animation: entrada 0.8s ease-out forwards; }

                @keyframes resaltar {
                    0%   { transform: translateY(0) scale(1); }
                    30%  { transform: translateY(-10px) scale(1.03); }
                    60%  { transform: translateY(-4px) scale(1.01); }
                    100% { transform: translateY(-6px) scale(1.02); }
                }
                .li-resaltar:hover { animation: resaltar .45s ease-out forwards; }

                @media (prefers-reduced-motion: reduce) {
                    .anim-entrada { opacity: 1; }
                    .anim-entrada-activa { animation: none; }
                    .li-resaltar:hover { animation: none; }
                }
            `}</style>

            <header className="border-b border-gray-200 bg-white">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                    <img src={imgLogo} alt="TrackEat" className="h-8 w-auto" />

                    <nav aria-label="Secciones de la página">
                        <ul className="flex items-center gap-8 text-sm text-gray-600">
                            <li><a href="#que-es" className="hover:text-gray-900">Qué es TrackEat</a></li>
                            <li><a href="#por-que-usar" className="hover:text-gray-900">Por qué usar TrackEat</a></li>
                            <li><a href="#nosotros" className="hover:text-gray-900">Acerca de nosotros</a></li>
                        </ul>
                    </nav>
                </div>
            </header>

            <main>
                {/* HERO — se anima solo, al cargar */}
                <section className="anim-entrada anim-entrada-activa bg-white">
                    <div className="mx-auto grid max-w-6xl grid-cols-1 items-center gap-12 px-6 py-20 lg:grid-cols-2">
                        <div>
                            <p className="mb-4 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <span className="h-1.5 w-1.5 rounded-full bg-orange-600"></span>
                                Sistema de gestión y comandas
                            </p>

                            <h1
                                tabIndex={0}
                                className={`text-4xl font-bold leading-tight text-gray-900 ${focoEncabezado}`}
                            >
                                Pedidos y seguimiento en tiempo real para restaurantes y food trucks.
                            </h1>

                            <p className="mt-6 max-w-md leading-relaxed text-gray-600">
                                Optimiza la experiencia de tus comensales y el trabajo de tu equipo de
                                cocina. Menú digital accesible, toma de comandas inmediata y monitoreo
                                de estados sin fricciones.
                            </p>

                            <div className="mt-8 flex items-center gap-4">
                                <a href="#que-es" className="rounded-md bg-orange-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-orange-700">
                                    Conocer más
                                </a>
                            </div>

                            <hr className="my-8 border-gray-200" />

                            <ul className="flex flex-wrap gap-x-8 gap-y-2 text-sm text-gray-500">
                                <li>Capacitación pausada del software</li>
                                <li>Puesta en marcha inmediata</li>
                            </ul>
                        </div>

                        <figure className="rounded-2xl border border-gray-200 bg-gray-50 p-6">
                            <div className="mb-4 flex items-center justify-between text-xs">
                                <span className="inline-flex items-center gap-2 font-semibold uppercase tracking-wide text-orange-700">
                                    <span className="h-1.5 w-1.5 rounded-full bg-orange-600"></span>
                                    Experiencia culinaria eficiente
                                </span>
                                <span className="font-semibold uppercase tracking-wide text-gray-400">
                                    Tiempo real
                                </span>
                            </div>

                            <img
                                src={imgChef}
                                alt="Ilustración de un chef sosteniendo una charola con un platillo"
                                className="mx-auto block h-64 w-64"
                            />

                            <figcaption className="mt-4 flex items-center justify-between text-xs text-gray-500">
                                <span>Servicio sincronizado</span>
                                <span>Cocina & Salón conectados</span>
                            </figcaption>
                        </figure>
                    </div>
                </section>

                {/* QUÉ ES TRACKEAT — se anima al hacer scroll hasta aquí */}
                <section
                    id="que-es"
                    ref={queEs.ref}
                    className={`anim-entrada bg-gray-50 ${queEs.visible ? 'anim-entrada-activa' : ''}`}
                >
                    <div className="mx-auto max-w-6xl px-6 py-20">
                        <p className="mb-4 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <span className="h-1.5 w-1.5 rounded-full bg-orange-600"></span>
                            Sistema integral
                        </p>
                        <h2
                            tabIndex={0}
                            className={`text-3xl font-bold text-gray-900 ${focoEncabezado}`}
                        >
                            ¿Qué es TrackEat?
                        </h2>
                        <p className="mt-4 max-w-2xl leading-relaxed text-gray-600">
                            TrackEat es un sistema integral de pedidos y gestión para restaurantes y
                            food trucks que conecta en tiempo real a comensales, meseros y equipo de
                            cocina, eliminando esperas y optimizando cada paso de la orden.
                        </p>

                        <ul className="mt-12 grid grid-cols-1 gap-6 md:grid-cols-3">
                            <li className="li-resaltar rounded-xl border border-gray-200 bg-white p-6">
                                <div className="mb-4 h-10 w-10 rounded-lg bg-orange-50"></div>
                                <p className="text-xs font-semibold uppercase tracking-wide text-gray-400">01 / Menú digital</p>
                                <h3 className="mt-2 font-semibold text-gray-900">Menú Digital Interactivo</h3>
                                <p className="mt-2 text-sm leading-relaxed text-gray-600">
                                    Los clientes exploran la carta completa con fotos, descripciones
                                    claras y precios actualizados directamente desde cualquier
                                    dispositivo móvil, sin necesidad de descargas pesadas.
                                </p>
                                <hr className="my-4 border-gray-200" />
                                <p className="text-xs uppercase tracking-wide text-gray-400">Acceso vía QR o terminal</p>
                            </li>

                            <li className="li-resaltar rounded-xl border border-gray-200 bg-white p-6">
                                <div className="mb-4 h-10 w-10 rounded-lg bg-orange-50"></div>
                                <p className="text-xs font-semibold uppercase tracking-wide text-gray-400">02 / Comandas</p>
                                <h3 className="mt-2 font-semibold text-gray-900">Gestión Ágil de Comandas</h3>
                                <p className="mt-2 text-sm leading-relaxed text-gray-600">
                                    Toma y despacho de órdenes instantáneo sin los habituales errores de
                                    comandeo manual. Las notas especiales y modificaciones llegan sin
                                    alteraciones directo al personal de cocina.
                                </p>
                                <hr className="my-4 border-gray-200" />
                                <p className="text-xs uppercase tracking-wide text-gray-400">Cero extravíos de pedidos</p>
                            </li>

                            <li className="li-resaltar rounded-xl border border-gray-200 bg-white p-6">
                                <div className="mb-4 h-10 w-10 rounded-lg bg-orange-50"></div>
                                <p className="text-xs font-semibold uppercase tracking-wide text-gray-400">03 / Tiempo real</p>
                                <h3 className="mt-2 font-semibold text-gray-900">Monitoreo en Tiempo Real</h3>
                                <p className="mt-2 text-sm leading-relaxed text-gray-600">
                                    Visibilidad simultánea del estado del pedido: "Recibido", "En
                                    preparación" y "Listo para entrega". Tanto comensales como
                                    administración conocen el ritmo exacto del servicio.
                                </p>
                                <hr className="my-4 border-gray-200" />
                                <p className="text-xs uppercase tracking-wide text-gray-400">Sincronización en vivo</p>
                            </li>
                        </ul>
                    </div>
                </section>

                {/* POR QUÉ USAR TRACKEAT — se anima al hacer scroll hasta aquí */}
                <section
                    id="por-que-usar"
                    ref={porQueUsar.ref}
                    className={`anim-entrada bg-gray-50 ${porQueUsar.visible ? 'anim-entrada-activa' : ''}`}
                >
                    <div className="mx-auto max-w-6xl px-6 py-20">
                        <div className="rounded-2xl border border-gray-200 bg-white p-10 shadow-sm">
                            <p className="mb-4 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-orange-600">
                                <span className="h-1.5 w-1.5 rounded-full bg-orange-600"></span>
                                Beneficios clave
                            </p>
                            <h2
                                tabIndex={0}
                                className={`text-2xl font-bold text-gray-900 ${focoEncabezado}`}
                            >
                                ¿Por qué usar TrackEat?
                            </h2>
                            <p className="mt-3 max-w-2xl leading-relaxed text-gray-600">
                                Una plataforma pensada para responder a las exigencias operativas
                                diarias de negocios gastronómicos modernos.
                            </p>

                            <dl className="mt-10 grid grid-cols-1 gap-6 md:grid-cols-3">
                                <div className="rounded-lg border border-gray-200 p-6">
                                    <dt className="text-3xl font-bold text-orange-600">-35%</dt>
                                    <dd className="mt-2 font-medium text-gray-900">Optimización de tiempos en cocina</dd>
                                    <dd className="mt-2 text-sm leading-relaxed text-gray-600">
                                        Reducción tangible en los tiempos de espera y rotación
                                        optimizada durante picos de servicio con pantallas KDS
                                        sincronizadas.
                                    </dd>
                                    <dd className="mt-3 text-xs uppercase tracking-wide text-gray-400">Métricas KDS integradas</dd>
                                </div>

                                <div className="rounded-lg border border-gray-200 p-6">
                                    <dt className="text-3xl font-bold text-gray-900">0%</dt>
                                    <dd className="mt-2 font-medium text-gray-900">Cero comisiones ocultas / control total</dd>
                                    <dd className="mt-2 text-sm leading-relaxed text-gray-600">
                                        Tarifa transparente y predecible sin porcentajes abusivos por
                                        comensal o ticket atendido. Tu ingreso es completamente tuyo.
                                    </dd>
                                    <dd className="mt-3 text-xs uppercase tracking-wide text-gray-400">Retención íntegra de ingresos</dd>
                                </div>

                                <div className="rounded-lg border border-gray-200 p-6">
                                    <dt className="text-3xl font-bold text-gray-900">{'<15m'}</dt>
                                    <dd className="mt-2 font-medium text-gray-900">Fácil adopción sin hardware costoso</dd>
                                    <dd className="mt-2 text-sm leading-relaxed text-gray-600">
                                        Funciona en tabletas, smartphones y computadoras estándar.
                                        Carga tu menú y comienza a recibir pedidos en minutos.
                                    </dd>
                                    <dd className="mt-3 text-xs uppercase tracking-wide text-gray-400">Plug & play sin dependencias</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </section>

                {/* ACERCA DE NOSOTROS — se anima al hacer scroll, tarjetas con flip */}
                <section
                    id="nosotros"
                    ref={nosotros.ref}
                    className={`anim-entrada bg-gray-50 ${nosotros.visible ? 'anim-entrada-activa' : ''}`}
                >
                    <div className="mx-auto max-w-6xl px-6 py-20">
                        <p className="mb-4 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <span className="h-1.5 w-1.5 rounded-full bg-orange-600"></span>
                            Equipo de desarrollo
                        </p>
                        <h2
                            tabIndex={0}
                            className={`text-3xl font-bold text-gray-900 ${focoEncabezado}`}
                        >
                            Acerca de nosotros
                        </h2>
                        <p className="mt-4 max-w-2xl leading-relaxed text-gray-600">
                            Somos estudiantes de Ingeniería en Sistemas Computacionales, apasionados
                            por la tecnología y preparándonos para ser desarrolladores de software de
                            alto impacto.
                        </p>
                        <p className="mt-2 max-w-2xl leading-relaxed text-gray-600">
                            Creamos TrackEat como una solución práctica y moderna para el sector
                            gastronómico, uniendo ingeniería de software, arquitectura en tiempo real
                            y diseño centrado en el usuario para resolver problemas reales de
                            restaurantes y food trucks.
                        </p>

                        <div className="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="[perspective:1000px]">
                                <div className="relative h-80 w-full [transform-style:preserve-3d] transition-transform duration-700 ease-out hover:[transform:rotateY(180deg)] motion-reduce:transition-none motion-reduce:hover:[transform:none]">
                                    <article className="absolute inset-0 flex flex-col rounded-xl border border-gray-200 bg-white p-6 text-center [backface-visibility:hidden]">
                                        <div className="mx-auto mb-4 h-12 w-12 rounded-lg bg-orange-50"></div>
                                        <h3 className="font-semibold text-gray-900">Angélica Menchaca Rueda</h3>
                                        <p className="mt-1 text-xs font-semibold uppercase tracking-wide text-orange-600">
                                            Desarrollador Frontend
                                        </p>
                                        <p className="mt-3 text-sm leading-relaxed text-gray-600">
                                            Enfocado en el diseño de interfaces limpias, accesibilidad
                                            web y la experiencia interactiva para clientes y comensales.
                                        </p>
                                        <a href="mailto:angie@correo.com" className="mt-4 block text-sm text-orange-600 hover:underline">
                                            angie@correo.com
                                        </a>
                                    </article>
                                    <div className="absolute inset-0 overflow-hidden rounded-xl border border-gray-200 [backface-visibility:hidden] [transform:rotateY(180deg)]">
                                        <img src={imgAngie} alt="Foto de Integrante 1" className="h-full w-full object-cover" />
                                    </div>
                                </div>
                            </div>

                            <div className="[perspective:1000px]">
                                <div className="relative h-80 w-full [transform-style:preserve-3d] transition-transform duration-700 ease-out hover:[transform:rotateY(180deg)] motion-reduce:transition-none motion-reduce:hover:[transform:none]">
                                    <article className="absolute inset-0 flex flex-col rounded-xl border border-gray-200 bg-white p-6 text-center [backface-visibility:hidden]">
                                        <div className="mx-auto mb-4 h-12 w-12 rounded-lg bg-orange-50"></div>
                                        <h3 className="font-semibold text-gray-900">Andryk Manuel León Tapia</h3>
                                        <p className="mt-1 text-xs font-semibold uppercase tracking-wide text-orange-600">
                                            Desarrollador Backend
                                        </p>
                                        <p className="mt-3 text-sm leading-relaxed text-gray-600">
                                            Especializado en la lógica de negocio, APIs en tiempo real
                                            y la sincronización confiable del flujo de comandas KDS.
                                        </p>
                                        <a href="mailto:andryk@correo.com" className="mt-4 block text-sm text-orange-600 hover:underline">
                                            andryk@correo.com
                                        </a>
                                    </article>
                                    <div className="absolute inset-0 overflow-hidden rounded-xl border border-gray-200 [backface-visibility:hidden] [transform:rotateY(180deg)]">
                                        <img src={imgAndryk} alt="Foto de Integrante 2" className="h-full w-full object-cover" />
                                    </div>
                                </div>
                            </div>

                            <div className="[perspective:1000px]">
                                <div className="relative h-80 w-full [transform-style:preserve-3d] transition-transform duration-700 ease-out hover:[transform:rotateY(180deg)] motion-reduce:transition-none motion-reduce:hover:[transform:none]">
                                    <article className="absolute inset-0 flex flex-col rounded-xl border border-gray-200 bg-white p-6 text-center [backface-visibility:hidden]">
                                        <div className="mx-auto mb-4 h-12 w-12 rounded-lg bg-orange-50"></div>
                                        <h3 className="font-semibold text-gray-900">Daniel Alexander Estrada Cosio</h3>
                                        <p className="mt-1 text-xs font-semibold uppercase tracking-wide text-orange-600">
                                            Base de Datos & Cloud
                                        </p>
                                        <p className="mt-3 text-sm leading-relaxed text-gray-600">
                                            A cargo del modelado de datos, optimización de consultas
                                            concurrentes y la estabilidad de la infraestructura en la nube.
                                        </p>
                                        <a href="mailto:Daniel@correo.com" className="mt-4 block text-sm text-orange-600 hover:underline">
                                            Daniel@correo.com
                                        </a>
                                    </article>
                                    <div className="absolute inset-0 overflow-hidden rounded-xl border border-gray-200 [backface-visibility:hidden] [transform:rotateY(180deg)]">
                                        <img src={imgDaniel} alt="Foto de Integrante 3" className="h-full w-full object-cover" />
                                    </div>
                                </div>
                            </div>

                            <div className="[perspective:1000px]">
                                <div className="relative h-80 w-full [transform-style:preserve-3d] transition-transform duration-700 ease-out hover:[transform:rotateY(180deg)] motion-reduce:transition-none motion-reduce:hover:[transform:none]">
                                    <article className="absolute inset-0 flex flex-col rounded-xl border border-gray-200 bg-white p-6 text-center [backface-visibility:hidden]">
                                        <div className="mx-auto mb-4 h-12 w-12 rounded-lg bg-orange-50"></div>
                                        <h3 className="font-semibold text-gray-900">Carlos Omar Celis Calzada</h3>
                                        <p className="mt-1 text-xs font-semibold uppercase tracking-wide text-orange-600">
                                            QA & Arquitectura
                                        </p>
                                        <p className="mt-3 text-sm leading-relaxed text-gray-600">
                                            Garantizando la fiabilidad del software mediante pruebas
                                            continuas, control de calidad y validación de requerimientos.
                                        </p>
                                        <a href="mailto:omar@correo.com" className="mt-4 block text-sm text-orange-600 hover:underline">
                                            omar@correo.com
                                        </a>
                                    </article>
                                    <div className="absolute inset-0 overflow-hidden rounded-xl border border-gray-200 [backface-visibility:hidden] [transform:rotateY(180deg)]">
                                        <img src={imgOmar} alt="Foto de Integrante 4" className="h-full w-full object-cover" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* CTA FINAL — se anima al hacer scroll hasta aquí */}
                <section
                    ref={cta.ref}
                    className={`anim-entrada bg-gray-50 ${cta.visible ? 'anim-entrada-activa' : ''}`}
                >
                    <div className="mx-auto max-w-3xl px-6 py-20 text-center">
                        <p className="mb-4 text-xs font-semibold uppercase tracking-wide text-gray-500">Comienza hoy</p>
                        <h2 className="text-3xl font-bold text-gray-900">
                            Eleva la sincronización de tu cocina y la satisfacción de tus clientes
                        </h2>
                        <p className="mt-4 leading-relaxed text-gray-600">
                            Conoce TrackEat en acción y comunícate directamente con el equipo
                            desarrollador para dudas, integración o demostraciones guiadas.
                        </p>
                        <div className="mt-8 flex items-center justify-center gap-4">
                            <a href="#nosotros" className="rounded-md bg-orange-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-orange-700">
                                Contactar al equipo
                            </a>
                            <a href="#que-es" className="rounded-md border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Ver funcionalidades
                            </a>
                        </div>
                    </div>
                </section>
            </main>

            <footer className="bg-gray-900 text-gray-300">
                <div className="mx-auto max-w-6xl px-6 py-16">
                    <div className="grid grid-cols-1 gap-10 lg:grid-cols-2">
                        <div>
                            <img src="/logo-white.svg" alt="TrackEat" className="h-6 w-auto" />
                            <p className="mt-4 max-w-sm text-sm leading-relaxed text-gray-400">
                                Plataforma integral de gestión y seguimiento de pedidos en tiempo
                                real. Proyecto de software desarrollado por estudiantes de
                                Ingeniería en Sistemas Computacionales.
                            </p>
                        </div>

                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Contacto directo con los desarrolladores
                            </p>
                            <address className="mt-4 grid grid-cols-1 gap-3 text-sm not-italic sm:grid-cols-2">
                                <p>Integrante 1 — <a href="mailto:contacto1@correo.com" className="text-orange-500 hover:underline">contacto1@correo.com</a></p>
                                <p>Integrante 2 — <a href="mailto:contacto2@correo.com" className="text-orange-500 hover:underline">contacto2@correo.com</a></p>
                                <p>Integrante 3 — <a href="mailto:contacto3@correo.com" className="text-orange-500 hover:underline">contacto3@correo.com</a></p>
                                <p>Integrante 4 — <a href="mailto:contacto4@correo.com" className="text-orange-500 hover:underline">contacto4@correo.com</a></p>
                            </address>
                        </div>
                    </div>

                    <hr className="my-10 border-gray-700" />

                    <div className="flex flex-col items-center justify-between gap-2 text-xs text-gray-500 sm:flex-row">
                        <p>© 2025 TrackEat. Desarrollado con dedicación para la optimización gastronómica.</p>
                        <p className="uppercase tracking-wide">Ingeniería en Sistemas Computacionales</p>
                    </div>
                </div>
            </footer>
        </>
    );
}