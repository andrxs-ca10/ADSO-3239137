import { useEffect, useState } from 'react'

const dueDateFormat = new Intl.DateTimeFormat('es-CO', {
  dateStyle: 'short',
  timeStyle: 'short',
  timeZone: 'America/Bogota',
})

export default function OverdueLoans({ apiBaseUrl, token }) {
  const [items, setItems] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    const controller = new AbortController()

    async function loadOverdueLoans() {
      try {
        const response = await fetch(`${apiBaseUrl}/loans/overdue`, {
          headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
          },
          signal: controller.signal,
        })

        if (!response.ok) {
          throw new Error('No se pudieron cargar los juegos vencidos.')
        }

        const data = await response.json()
        if (!controller.signal.aborted) setItems(data.items)
      } catch (requestError) {
        if (!controller.signal.aborted) setError(requestError.message)
      }
    }

    loadOverdueLoans()
    return () => controller.abort()
  }, [apiBaseUrl, token])

  return (
    <section aria-labelledby="overdue-loans-title">
      <h2 id="overdue-loans-title">Juegos vencidos</h2>

      {error && <p role="alert">{error}</p>}
      {!items && !error && <p role="status">Cargando juegos vencidos…</p>}
      {items?.length === 0 && <p>No hay juegos vencidos pendientes de devolución.</p>}

      {items?.length > 0 && (
        <table>
          <thead>
            <tr>
              <th scope="col">Juego</th>
              <th scope="col">Socio</th>
              <th scope="col">Vencimiento</th>
              <th scope="col">Días de atraso</th>
            </tr>
          </thead>
          <tbody>
            {items.map((item) => (
              <tr key={item.item_id}>
                <td>{item.game_title}</td>
                <td>{item.member_name}</td>
                <td>{dueDateFormat.format(new Date(item.due_at))}</td>
                <td>{item.days_late}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </section>
  )
}