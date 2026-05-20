<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps({
  // Cada slide: { imagen, titulo, subtitulo, botonTexto, botonA }
  slides: { type: Array, required: true },
  intervalo: { type: Number, default: 5000 },
})

const indice = ref(0)
const pausado = ref(false)
let temporizador = null

function siguiente() {
  indice.value = (indice.value + 1) % props.slides.length
}

function anterior() {
  indice.value = (indice.value - 1 + props.slides.length) % props.slides.length
}

function irA(i) {
  indice.value = i
}

function iniciar() {
  detener()
  temporizador = setInterval(() => {
    if (!pausado.value) siguiente()
  }, props.intervalo)
}

function detener() {
  if (temporizador) {
    clearInterval(temporizador)
    temporizador = null
  }
}

// Soporte de swipe en móvil.
const inicioTactil = ref(null)
function onTouchStart(ev) {
  inicioTactil.value = ev.touches[0].clientX
}
function onTouchEnd(ev) {
  if (inicioTactil.value == null) return
  const fin = ev.changedTouches[0].clientX
  const delta = fin - inicioTactil.value
  if (Math.abs(delta) > 40) {
    if (delta < 0) siguiente()
    else anterior()
  }
  inicioTactil.value = null
}

onMounted(iniciar)
onBeforeUnmount(detener)
</script>

<template>
  <div
    class="hero-carousel"
    role="region"
    aria-roledescription="carrusel"
    aria-label="Destacados de NovaCommerce"
    @mouseenter="pausado = true"
    @mouseleave="pausado = false"
    @touchstart.passive="onTouchStart"
    @touchend.passive="onTouchEnd"
  >
    <div
      v-for="(slide, i) in slides"
      :key="i"
      class="hero-carousel__slide"
      :class="{ 'hero-carousel__slide--activo': i === indice }"
      :style="{ backgroundImage: `url(${slide.imagen})` }"
      :aria-hidden="i !== indice"
      role="group"
      aria-roledescription="slide"
    >
      <div class="hero-carousel__overlay" aria-hidden="true"></div>
      <div class="hero-carousel__contenido">
        <div class="max-w-xl">
          <h2 class="text-3xl font-extrabold leading-tight text-white drop-shadow-md sm:text-4xl lg:text-5xl">
            {{ slide.titulo }}
          </h2>
          <p class="mt-4 max-w-[500px] text-sm text-slate-100/95 sm:text-base lg:text-lg">
            {{ slide.subtitulo }}
          </p>
          <RouterLink
            :to="slide.botonA"
            :tabindex="i === indice ? 0 : -1"
            class="mt-6 inline-flex items-center gap-2 rounded-lg bg-white px-5 py-3 text-sm font-semibold text-primary-700 shadow-md transition-colors hover:bg-primary-600 hover:text-white sm:px-6 sm:text-base"
          >
            {{ slide.botonTexto }}
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
            </svg>
          </RouterLink>
        </div>
      </div>
    </div>

    <button
      type="button"
      class="hero-carousel__flecha hero-carousel__flecha--izq"
      aria-label="Slide anterior"
      @click="anterior"
    >
      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
      </svg>
    </button>
    <button
      type="button"
      class="hero-carousel__flecha hero-carousel__flecha--der"
      aria-label="Slide siguiente"
      @click="siguiente"
    >
      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
      </svg>
    </button>

    <div class="hero-carousel__indicadores" role="tablist" aria-label="Selector de slide">
      <button
        v-for="(_, i) in slides"
        :key="i"
        type="button"
        class="hero-carousel__punto"
        :class="{ 'hero-carousel__punto--activo': i === indice }"
        :aria-label="`Ir al slide ${i + 1}`"
        :aria-selected="i === indice"
        role="tab"
        @click="irA(i)"
      ></button>
    </div>
  </div>
</template>

<style scoped>
.hero-carousel {
  position: relative;
  overflow: hidden;
  border-radius: 1rem;
  background-color: #0f172a;
  height: 300px;
  box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.3);
}
@media (min-width: 768px) {
  .hero-carousel { height: 500px; border-radius: 1.25rem; }
}

.hero-carousel__slide {
  position: absolute;
  inset: 0;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  opacity: 0;
  transition: opacity 700ms ease-in-out;
  pointer-events: none;
}

.hero-carousel__slide--activo {
  opacity: 1;
  pointer-events: auto;
}

.hero-carousel__overlay {
  position: absolute;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.4);
}

.hero-carousel__contenido {
  position: relative;
  z-index: 1;
  height: 100%;
  display: flex;
  align-items: center;
  padding: 1.5rem 1.25rem 3rem;
}
@media (min-width: 640px) {
  .hero-carousel__contenido { padding: 2rem 3rem 3rem; }
}
@media (min-width: 1024px) {
  .hero-carousel__contenido { padding: 3rem 5rem; }
}

.hero-carousel__flecha {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  display: none;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  background-color: rgba(255, 255, 255, 0.25);
  color: white;
  border-radius: 9999px;
  backdrop-filter: blur(4px);
  transition: background-color 200ms;
  z-index: 2;
}
.hero-carousel__flecha:hover { background-color: rgba(255, 255, 255, 0.45); }
.hero-carousel__flecha:focus-visible {
  outline: 2px solid white;
  outline-offset: 2px;
}
.hero-carousel__flecha--izq { left: 1rem; }
.hero-carousel__flecha--der { right: 1rem; }
@media (min-width: 768px) {
  .hero-carousel__flecha { display: inline-flex; }
}

.hero-carousel__indicadores {
  position: absolute;
  bottom: 1rem;
  left: 0;
  right: 0;
  display: flex;
  justify-content: center;
  gap: 0.5rem;
  z-index: 2;
}

.hero-carousel__punto {
  width: 0.625rem;
  height: 0.625rem;
  border-radius: 9999px;
  background-color: rgba(255, 255, 255, 0.5);
  transition: background-color 200ms, width 200ms;
}
.hero-carousel__punto:hover { background-color: rgba(255, 255, 255, 0.8); }
.hero-carousel__punto:focus-visible {
  outline: 2px solid white;
  outline-offset: 2px;
}
.hero-carousel__punto--activo {
  background-color: white;
  width: 1.75rem;
}
</style>
