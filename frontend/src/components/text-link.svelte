<script>
    import { Link } from '@inertiajs/svelte';
    import { cn } from '@/lib/utils';

    let { class: className = '', children, ...rest } = $props();
</script>

<Link
    class={cn(
        'text-foreground underline decoration-border underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!',
        className,
    )}
    {...rest}
>
    {@render children?.()}
</Link>
