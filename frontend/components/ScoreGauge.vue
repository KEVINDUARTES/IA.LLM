<script setup lang="ts">
const props = defineProps<{ score: number }>()

const radius = 54
const circumference = 2 * Math.PI * radius
const dashOffset = computed(() => circumference - (circumference * props.score) / 100)

const gaugeColor = computed(() => {
  if (props.score >= 75) return '#16a34a'
  if (props.score >= 50) return '#d97706'
  return '#dc2626'
})

const textColor = computed(() => {
  if (props.score >= 75) return 'text-green-600'
  if (props.score >= 50) return 'text-amber-600'
  return 'text-red-600'
})

const label = computed(() => {
  if (props.score >= 75) return 'Perfil fuerte'
  if (props.score >= 50) return 'Perfil con brechas'
  return 'Perfil débil para el puesto'
})
</script>

<template>
  <div class="flex flex-col items-center gap-2">
    <div class="relative">
      <svg width="160" height="160" viewBox="0 0 160 160">
        <!-- Track -->
        <circle
          cx="80" cy="80" :r="radius"
          fill="none" stroke="#e5e7eb" stroke-width="14"
        />
        <!-- Progress -->
        <circle
          cx="80" cy="80" :r="radius"
          fill="none"
          :stroke="gaugeColor"
          stroke-width="14"
          stroke-linecap="round"
          :stroke-dasharray="circumference"
          :stroke-dashoffset="dashOffset"
          transform="rotate(-90 80 80)"
          style="transition: stroke-dashoffset 0.8s ease"
        />
      </svg>
      <!-- Score number overlaid on the SVG -->
      <div class="absolute inset-0 flex flex-col items-center justify-center">
        <span class="text-4xl font-black" :class="textColor">{{ score }}</span>
        <span class="text-sm text-gray-400 font-medium">/ 100</span>
      </div>
    </div>
    <span class="text-sm font-semibold" :class="textColor">{{ label }}</span>
  </div>
</template>
