<script>
    import Check from '@lucide/svelte/icons/check';
    import { cn } from '@/lib/utils';

    let { class: className = '', checked = $bindable(false), ...rest } = $props();
</script>

<span class="relative inline-flex size-5 shrink-0">
    <input
        type="checkbox"
        bind:checked
        class={cn(
            'peer size-5 shrink-0 appearance-none rounded-sm border border-input ring-offset-background focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 checked:border-accent-foreground checked:bg-primary',
            className,
        )}
        {...rest}
    />
    <Check class="pointer-events-none absolute inset-0 m-auto size-3.5 stroke-[3] text-primary-foreground opacity-0 peer-checked:opacity-100" />
</span>
