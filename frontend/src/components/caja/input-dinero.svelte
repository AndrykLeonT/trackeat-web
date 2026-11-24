<script>
    import Input from '@/components/ui/input.svelte';
    import { limpiarDinero } from '@/lib/caja';

    // Campo de dinero: no deja escribir letras ni símbolos, solo números y un punto con hasta dos decimales.
    // El servidor vuelve a validar el importe; esto solo evita capturas equivocadas.
    let { value = $bindable(''), ...rest } = $props();

    // Bloquea una tecla inválida antes de que aparezca. Si llega un texto de varios caracteres
    // de golpe (teclados de celular, dictado), no se bloquea: alEscribir lo limpia.
    function antesDeEscribir(e) {
        if (e.inputType === 'insertText' && e.data?.length === 1 && /[^\d.]/.test(e.data)) {
            e.preventDefault();
        }
    }

    // Limpia lo que llegue por otras vías (pegar, autocompletar) y respeta el límite de decimales.
    function alEscribir(e) {
        const limpio = limpiarDinero(e.currentTarget.value);
        if (limpio !== e.currentTarget.value) {
            e.currentTarget.value = limpio;
        }
        value = limpio;
    }
</script>

<Input bind:value inputmode="decimal" autocomplete="off" onbeforeinput={antesDeEscribir} oninput={alEscribir} {...rest} />
