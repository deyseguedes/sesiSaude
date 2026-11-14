const unavailable = 'Serviço temporariamente indisponível. Tente novamente em instantes.'
const invalidResponse = 'O serviço enviou uma resposta inválida. Tente novamente.'

function fallbackMessage(status) {
  if (status === 401) return 'Sua sessão expirou. Entre novamente.'
  if (status === 403) return 'Você não tem permissão para realizar esta ação.'
  if (status >= 500) return unavailable
  return 'Não foi possível concluir a solicitação. Tente novamente.'
}

export async function requestJson(url, options = {}) {
  if (import.meta.env?.VITE_SHARE_DEMO === 'true') {
    const { demoRequest } = await import('./share-demo.js')
    return demoRequest(url, options)
  }
  let response, text
  try {
    response = await fetch(url, { credentials: 'same-origin', ...options })
    text = await response.text()
  } catch (error) {
    if (error.name === 'AbortError') throw error
    throw new Error(unavailable)
  }

  let payload
  try {
    payload = JSON.parse(text)
  } catch {
    throw new Error(response.ok ? invalidResponse : fallbackMessage(response.status))
  }

  if (!payload || typeof payload !== 'object' || Array.isArray(payload)
    || !Object.hasOwn(payload, 'data') || !Object.hasOwn(payload, 'error')) {
    throw new Error(response.ok ? invalidResponse : fallbackMessage(response.status))
  }
  if (!response.ok || payload.error !== null) {
    const message = payload.error?.message
    throw new Error(typeof message === 'string' && message.trim() ? message : fallbackMessage(response.status))
  }
  return payload
}
