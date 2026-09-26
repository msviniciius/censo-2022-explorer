import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import HomeView from './HomeView.vue'

describe('bootstrap integration', () => {
  it('requests the API on the same origin and presents readiness', async () => {
    const request = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: { status: 'ok' } }),
    })
    vi.stubGlobal('fetch', request)

    const wrapper = mount(HomeView)
    expect(wrapper.get('[role="status"]').text()).toBe('Verificando conexão…')
    await flushPromises()

    expect(request).toHaveBeenCalledWith('/api/health', {
      headers: { Accept: 'application/json' },
    })
    expect(wrapper.get('[role="status"]').text()).toBe('Conexão disponível.')
    wrapper.unmount()
  })

  it('handles an unavailable backend without displaying internal errors', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 503 }))
    const wrapper = mount(HomeView)
    await flushPromises()

    expect(wrapper.get('[role="status"]').text()).toBe('Conexão indisponível.')
    wrapper.unmount()
  })
})
