<script>
    import logo from '../../assets/images/logo.png';
</script>

<div class="flex flex-col sm:flex-row items-center justify-between gap-2.5 sm:gap-4 text-xs text-gray-500 text-center sm:text-left">
    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 sm:gap-2.5">
        <img src={logo} alt="TrackEat" class="h-4.5 sm:h-5 w-auto object-contain opacity-80" />
        <span class="text-gray-300 hidden sm:inline">|</span>
        <p class="text-[11px] sm:text-xs">© 2026 TrackEat. Sistema de gestión en tiempo real.</p>
    </div>

    <div class="flex items-center justify-center gap-2 sm:gap-3 text-[11px] sm:text-xs text-gray-400">
        <span>TecNM La Paz · ISC</span>
        <span class="text-gray-300">·</span>
        <a
            href="mailto:L22310531@lapaz.tecnm.mx"
            class="hover:text-orange-600 transition-colors"
        >
            Contacto
        </a>
    </div>
</div>