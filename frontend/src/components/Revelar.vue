<script setup>
import { useRevelar } from '@/composables/useRevelar'

defineProps({
  delay: { type: Number, default: 0 },
})

const { elemento, visible } = useRevelar()
</script>

<template>
  <div
    ref="elemento"
    class="revelar"
    :class="{ visible }"
    :style="{ transitionDelay: `${delay}ms` }"
  >
    <slot />
  </div>
</template>
