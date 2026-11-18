import { useState } from 'react'

export default function MostRequestedGames({ apiBaseUrl, token }) {
  const [report, setReport] = useState(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  async function loadReport(event) {
    event.preventDefault()

    const fields = new FormData(event.currentTarget)
    const from = fields.get('from')
    const to = fields.get('to')

    setReport(null)
    setError('')

    if (to < from) {
      setError('La fecha final no puede ser anterior a la fecha inicial.')
      return
    }

    setLoading(true)

    try {
      const params = new URLSearchParams({ from, to })
      const response = await fetch(`${apiBaseUrl}/reports/most-requested?${params}`, {
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
      })

      const data = await response.json().catch(() => null)

      if (!response.ok || !Array.isArray(data?.rows)) {
        throw new Error(data?.message || 'No se pudo cargar el reporte.')
      }

      setReport(data)
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <section aria-labelledby="most-requested-title">
      <h2 id="most-requested-title">Juegos más solicitados</h2>

      <form onSubmit={loadReport}>
        <fieldset disabled={loading}>
          <legend>Periodo del reporte</legend>

          <label htmlFor="report-from">Desde</label>{' '}
          <input id="report-from" name="from" type="date" required />{' '}
          <label htmlFor="report-to">Hasta</label>{' '}
          <input id="report-to" name="to" type="date" required />{' '}
          <button type="submit">Consultar</button>
        </fieldset>
      </form>

      {loading && <p role="status">Cargando reporte…</p>}
      {error && <p role="alert">{error}</p>}
      {report?.rows.length === 0 && <p>No hubo préstamos en este periodo.</p>}

      {report?.rows.length > 0 && (
        <table>
          <caption>Del {report.from} al {report.to}</caption>
          <thead>
            <tr>
              <th scope="col">Juego</th>
              <th scope="col">Categoría</th>
              <th scope="col">Veces solicitado</th>
            </tr>
          </thead>
          <tbody>
            {report.rows.map((row) => (
              <tr key={JSON.stringify([row.game_id, row.game_title, row.category_name])}>
                <td>{row.game_title}</td>
                <td>{row.category_name}</td>
                <td>{row.times_requested}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </section>
  )
}