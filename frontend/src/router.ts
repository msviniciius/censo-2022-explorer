import { createRouter, createWebHistory } from 'vue-router'
import MunicipalityView from './views/MunicipalityView.vue'
import StateView from './views/StateView.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/municipios' },
    { path: '/municipios', name: 'municipios', component: MunicipalityView },
    { path: '/estados', name: 'estados', component: StateView },
    { path: '/:pathMatch(.*)*', redirect: '/municipios' },
  ],
})
