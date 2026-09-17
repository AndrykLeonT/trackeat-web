import { Head } from '@inertiajs/react';
import logo from "../../assets/images/logo.png";
import imgChef from "../../assets/images/chef.jpg";

export default function Landing() {
    return (
        <>
            <Head title="TrackEat" />

            <header className="border-b border-gray-200 bg-white">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                    <img src={logo} alt="TrackEat" className="h-8 w-auto" />

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
                {/* HERO */}
                <section className="bg-white">
                    <div className="mx-auto grid max-w-6xl grid-cols-1 items-center gap-12 px-6 py-20 lg:grid-cols-2">
                        <div>
                            <p className="mb-4 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <span className="h-1.5 w-1.5 rounded-full bg-orange-600"></span>
                                Sistema de gestión y comandas
                            </p>

                            <h1 className="text-4xl font-bold leading-tight text-gray-900">
                                Pedidos y seguimiento en tiempo real para restaurantes y food trucks.
                            </h1>

                            <p className="mt-6 max-w-md leading-relaxed text-gray-600">
                                Optimiza la experiencia de tus comensales y el trabajo de tu equipo de
                                cocina. Menú digital accesible, toma de comandas inmediata y monitoreo
                                de estados sin fricciones.
                            </p>

                            <div className="mt-8 flex items-center gap-4">
                                <a href="#" className="rounded-md bg-orange-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-orange-700">
                                    Solicitar demostración
                                </a>
                                <a href="#que-es" className="rounded-md border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
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

                {/* QUÉ ES TRACKEAT */}
                <section id="que-es" className="bg-gray-50">
                    <div className="mx-auto max-w-6xl px-6 py-20">
                        <p className="mb-4 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <span className="h-1.5 w-1.5 rounded-full bg-orange-600"></span>
                            Sistema integral
                        </p>
                        <h2 className="text-3xl font-bold text-gray-900">¿Qué es TrackEat?</h2>
                        <p className="mt-4 max-w-2xl leading-relaxed text-gray-600">
                            TrackEat es un sistema integral de pedidos y gestión para restaurantes y
                            food trucks que conecta en tiempo real a comensales, meseros y equipo de
                            cocina, eliminando esperas y optimizando cada paso de la orden.
                        </p>

                        <ul className="mt-12 grid grid-cols-1 gap-6 md:grid-cols-3">
                            <li className="rounded-xl border border-gray-200 bg-white p-6">
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

                            <li className="rounded-xl border border-gray-200 bg-white p-6">
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

                            <li className="rounded-xl border border-gray-200 bg-white p-6">
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

                {/* POR QUÉ USAR TRACKEAT */}
                <section id="por-que-usar" className="bg-white">
                    <div className="mx-auto max-w-6xl px-6 py-20">
                        <div className="rounded-2xl border border-gray-200 bg-white p-10 shadow-sm">
                            <p className="mb-4 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-orange-600">
                                <span className="h-1.5 w-1.5 rounded-full bg-orange-600"></span>
                                Beneficios clave
                            </p>
                            <h2 className="text-2xl font-bold text-gray-900">¿Por qué usar TrackEat?</h2>
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

                {/* ACERCA DE NOSOTROS */}
                <section id="nosotros" className="bg-gray-50">
                    <div className="mx-auto max-w-6xl px-6 py-20">
                        <p className="mb-4 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <span className="h-1.5 w-1.5 rounded-full bg-orange-600"></span>
                            Equipo de desarrollo
                        </p>
                        <h2 className="text-3xl font-bold text-gray-900">Acerca de nosotros</h2>
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
                            <article className="rounded-xl border border-gray-200 bg-white p-6 text-center">
                                <div className="mx-auto mb-4 h-12 w-12 rounded-lg bg-orange-50"></div>
                                <h3 className="font-semibold text-gray-900">Integrante 1</h3>
                                <p className="mt-1 text-xs font-semibold uppercase tracking-wide text-orange-600">
                                    Desarrollador Frontend
                                </p>
                                <p className="mt-3 text-sm leading-relaxed text-gray-600">
                                    Enfocado en el diseño de interfaces limpias, accesibilidad web y la
                                    experiencia interactiva para clientes y comensales.
                                </p>
                                <a href="mailto:contacto1@correo.com" className="mt-4 block text-sm text-orange-600 hover:underline">
                                    contacto1@correo.com
                                </a>
                            </article>

                            <article className="rounded-xl border border-gray-200 bg-white p-6 text-center">
                                <div className="mx-auto mb-4 h-12 w-12 rounded-lg bg-orange-50"></div>
                                <h3 className="font-semibold text-gray-900">Integrante 2</h3>
                                <p className="mt-1 text-xs font-semibold uppercase tracking-wide text-orange-600">
                                    Desarrollador Backend
                                </p>
                                <p className="mt-3 text-sm leading-relaxed text-gray-600">
                                    Especializado en la lógica de negocio, APIs en tiempo real y la
                                    sincronización confiable del flujo de comandas KDS.
                                </p>
                                <a href="mailto:contacto2@correo.com" className="mt-4 block text-sm text-orange-600 hover:underline">
                                    contacto2@correo.com
                                </a>
                            </article>

                            <article className="rounded-xl border border-gray-200 bg-white p-6 text-center">
                                <div className="mx-auto mb-4 h-12 w-12 rounded-lg bg-orange-50"></div>
                                <h3 className="font-semibold text-gray-900">Integrante 3</h3>
                                <p className="mt-1 text-xs font-semibold uppercase tracking-wide text-orange-600">
                                    Base de Datos & Cloud
                                </p>
                                <p className="mt-3 text-sm leading-relaxed text-gray-600">
                                    A cargo del modelado de datos, optimización de consultas
                                    concurrentes y la estabilidad de la infraestructura en la nube.
                                </p>
                                <a href="mailto:contacto3@correo.com" className="mt-4 block text-sm text-orange-600 hover:underline">
                                    contacto3@correo.com
                                </a>
                            </article>

                            <article className="rounded-xl border border-gray-200 bg-white p-6 text-center">
                                <div className="mx-auto mb-4 h-12 w-12 rounded-lg bg-orange-50"></div>
                                <h3 className="font-semibold text-gray-900">Integrante 4</h3>
                                <p className="mt-1 text-xs font-semibold uppercase tracking-wide text-orange-600">
                                    QA & Arquitectura
                                </p>
                                <p className="mt-3 text-sm leading-relaxed text-gray-600">
                                    Garantizando la fiabilidad del software mediante pruebas
                                    continuas, control de calidad y validación de requerimientos.
                                </p>
                                <a href="mailto:contacto4@correo.com" className="mt-4 block text-sm text-orange-600 hover:underline">
                                    contacto4@correo.com
                                </a>
                            </article>
                        </div>
                    </div>
                </section>

                {/* CTA FINAL */}
                <section className="bg-gray-50">
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
                            <img src={logo} alt="TrackEat" className="h-6 w-auto" />
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