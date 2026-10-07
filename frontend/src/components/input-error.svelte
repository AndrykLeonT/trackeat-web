<script>
    import { cn } from '@/lib/utils';

    let { message, class: className = '', ...rest } = $props();
</script>

{#if message}
    <p class={cn('text-sm text-destructive', className)} {...rest}>
        {message}
    </p>
{/if}
