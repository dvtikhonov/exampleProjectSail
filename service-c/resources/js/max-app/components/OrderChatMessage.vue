<script setup>
import { computed } from 'vue';

const props = defineProps({
    message: {
        type: Object,
        required: true,
    },
    isOwn: {
        type: Boolean,
        default: false,
    },
    perspective: {
        type: String,
        default: 'customer',
        validator: (value) => ['customer', 'admin'].includes(value),
    },
});

const formattedTime = computed(() => {
    try {
        return new Intl.DateTimeFormat('ru-RU', {
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(props.message.created_at));
    } catch {
        return '';
    }
});

const senderLabel = computed(() => {
    if (props.isOwn) {
        return 'Вы';
    }

    const sender = props.message.sender ?? {};
    const parts = [sender.first_name, sender.last_name].filter(Boolean);

    if (parts.length > 0) {
        return parts.join(' ');
    }

    if (sender.username) {
        return `@${sender.username}`;
    }

    if (props.message.author_type === 'admin') {
        return 'Оператор';
    }

    return props.perspective === 'admin' ? 'Клиент' : 'Оператор';
});
</script>

<template>
    <div
        class="flex"
        :class="isOwn ? 'justify-end' : 'justify-start'"
    >
        <div
            class="max-w-[85%] rounded-2xl px-3.5 py-2.5 shadow-sm"
            :class="isOwn
                ? 'rounded-br-md bg-max-primary text-white'
                : 'rounded-bl-md border border-gray-100 bg-white text-gray-900'"
        >
            <p
                class="mb-1 text-xs font-medium"
                :class="isOwn ? 'text-white/80' : 'text-max-muted'"
            >
                {{ senderLabel }}
            </p>
            <p class="whitespace-pre-wrap break-words text-sm leading-relaxed">{{ message.body }}</p>
            <p
                class="mt-1 text-right text-[10px]"
                :class="isOwn ? 'text-white/70' : 'text-max-muted'"
            >
                {{ formattedTime }}
            </p>
        </div>
    </div>
</template>
