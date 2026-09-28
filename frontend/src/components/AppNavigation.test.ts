import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter, RouterLink } from 'vue-router'
import AppNavigation from './AppNavigation.vue'

function mountNav(path = '/estados') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/municipios', name: 'municipios', component: { template: '<div />' } },
      { path: '/estados', name: 'estados', component: { template: '<div />' } },
    ],
  })
  const wrapper = mount(AppNavigation, { global: { plugins: [router] } })
  return { wrapper, router, path }
}

describe('AppNavigation.vue', () => {
  it('expõe as duas telas com rótulos textuais', () => {
    const { wrapper } = mountNav()
    const links = wrapper.findAllComponents(RouterLink)

    expect(links).toHaveLength(2)
    expect(links.map((link) => link.text())).toEqual(['Municípios', 'Estados'])
    expect(links.map((link) => link.props('to'))).toEqual(['/municipios', '/estados'])
  })

  it('mantém a navegação entre telas visível em telas estreitas', () => {
    const { wrapper } = mountNav()
    const links = wrapper.findAllComponents(RouterLink)

    for (const link of links) {
      // Nenhuma classe pode ocultar os links em telas estreitas.
      expect(link.classes()).not.toContain('hidden')
      expect(link.classes()).not.toContain('sm:flex')
    }

    expect(links[0]!.classes()).toContain('inline-flex')
    expect(links[1]!.classes()).toContain('inline-flex')
  })

  it('rotula a região de navegação principal', () => {
    const { wrapper } = mountNav()
    const nav = wrapper.find('nav')

    expect(nav.exists()).toBe(true)
    expect(nav.attributes('aria-label')).toBe('Navegação principal')
  })

  it('diferencia visualmente a rota ativa', async () => {
    const { wrapper, router } = mountNav()
    await router.push('/estados')
    await router.isReady()

    const links = wrapper.findAllComponents(RouterLink)
    expect(links[1]!.classes()).toContain('border-blue-500')
    expect(links[0]!.classes()).toContain('border-transparent')
  })
})
