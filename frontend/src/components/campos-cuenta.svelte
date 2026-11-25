<script>
    import Input from '@/components/ui/input.svelte';
    import Label from '@/components/ui/label.svelte';
    import InputError from '@/components/input-error.svelte';

    let { form } = $props();
    const fields = [
        { key: 'primer_nombre', label: 'Primer nombre', autocomplete: 'given-name', required: true },
        { key: 'segundo_nombre', label: 'Segundo nombre', autocomplete: 'additional-name' },
        { key: 'primer_apellido', label: 'Primer apellido', autocomplete: 'family-name' },
        { key: 'segundo_apellido', label: 'Segundo apellido' },
        { key: 'email', label: 'Correo electrónico', autocomplete: 'email', type: 'email', required: true, max: 255 },
        { key: 'telefono', label: 'Teléfono', autocomplete: 'tel', type: 'tel', max: 30 },
    ];
</script>

<div class="grid gap-4 sm:grid-cols-2">
    {#each fields as field (field.key)}
        <div class="grid gap-2">
            <Label for={field.key}>{field.label}</Label>
            <Input
                id={field.key}
                name={field.key}
                type={field.type ?? 'text'}
                autocomplete={field.autocomplete}
                maxlength={field.max ?? 80}
                required={field.required ?? false}
                bind:value={$form[field.key]}
            />
            <InputError message={$form.errors[field.key]} />
        </div>
    {/each}
</div>
