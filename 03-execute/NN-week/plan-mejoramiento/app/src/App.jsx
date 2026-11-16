import { useState } from 'react'
import Catalog from './Catalog.jsx'
import Members from './Members.jsx'

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api/v1'

export default function App() {
  const [session, setSession] = useState(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  async function handleLogin(event) {
    event.preventDefault()

    const form = new FormData(event.currentTarget)
    const username = form.get('username').trim()
    const password = form.get('password')

    setError('')
    setLoading(true)

    try {
      const response = await fetch(`${apiBaseUrl}/auth/login`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ username, password }),
      })

      const data = await response.json().catch(() => null)

      if (!response.ok) {
        setError(data?.message || 'No se pudo iniciar sesión.')
        return
      }

      if (!data?.access_token || !data.role) {
        setError('La API devolvió una respuesta de sesión incompleta.')
        return
      }

      setSession({ username, token: data.access_token, role: data.role })
    } catch {
      setError('No se pudo conectar con la API. Comprueba que Laravel esté activo.')
    } finally {
      setLoading(false)
    }
  }

  if (session) {
    return (
      <main>
        <h1>Ludoteca</h1>
        <p>Bienvenido, {session.username}.</p>
        <p>Rol {session.role === 'admin' ? 'administrador' : 'operador'}.</p>

        <button type="button" onClick={() => setSession(null)}>
          Cerrar sesión
        </button>

        <Catalog apiBaseUrl={apiBaseUrl} token={session.token} />
        <Members apiBaseUrl={apiBaseUrl} token={session.token} />
      </main>
    )
  }

  return (
    <main>
      <h1>Ludoteca</h1>
      <h2>Iniciar sesión</h2>

      <form onSubmit={handleLogin}>
        <p>
          <label htmlFor="username">Usuario</label><br />
          <input id="username" name="username" autoComplete="username" required />
        </p>
        <p>
          <label htmlFor="password">Contraseña</label><br />
          <input
            id="password"
            name="password"
            type="password"
            autoComplete="current-password"
            required
          />
        </p>

        {error && <p role="alert">{error}</p>}

        <button type="submit" disabled={loading}>
          {loading ? 'Ingresando…' : 'Ingresar'}
        </button>
      </form>
    </main>
  )
}