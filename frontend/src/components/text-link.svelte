<script>
    import { Link } from '@inertiajs/svelte';
    import { cn } from '@/lib/utils';

    let { class: className = '', children, ...rest } = $props();
</script>

<Link
    class={cn(
        'text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500',
        className,
    )}
    {...rest}
>
    {@render children?.()}
</Link>
