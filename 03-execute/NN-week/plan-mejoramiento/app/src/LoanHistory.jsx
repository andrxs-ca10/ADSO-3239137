import { useEffect, useState } from 'react'
import LoanReturns from './LoanReturns.jsx'

const statusLabels = {
  open: 'Abierto',
  overdue: 'Vencido',
  closed: 'Cerrado',
}

const dateFormat = new Intl.DateTimeFormat('es-CO', {
  dateStyle: 'short',
  timeStyle: 'short',
  timeZone: 'America/Bogota',
})

export default function LoanHistory({ apiBaseUrl, token, revision, members, onReturned }) {
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [result, setResult] = useState(null)
  const [error, setError] = useState('')
  const [selectedLoanId, setSelectedLoanId] = useState(null)

  const namesById = new Map(
    (members ?? []).map((member) => [member.id, member.full_name]),
  )

  useEffect(() => {
    const controller = new AbortController()
    const params = new URLSearchParams({ page: String(page), size: '10' })
    if (status) params.set('status', status)

    async function loadHistory() {
      setResult(null)
      setError('')

      try {
        const response = await fetch(`${apiBaseUrl}/loans?${params}`, {
          headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
          },
          signal: controller.signal,
        })

        if (!response.ok) {
          throw new Error('No se pudo cargar el historial de préstamos.')
        }

        const data = await response.json()
        if (!controller.signal.aborted) setResult(data)
      } catch (requestError) {
        if (!controller.signal.aborted) setError(requestError.message)
      }
    }

    loadHistory()
    return () => controller.abort()
  }, [apiBaseUrl, token, revision, page, status])

  return (
    <section aria-labelledby="loan-history-title">
      <h3 id="loan-history-title">Historial de préstamos</h3>

      <label htmlFor="loan-status">Estado</label>{' '}
      <select
        id="loan-status"
        value={status}
        onChange={(event) => {
          setStatus(event.target.value)
          setPage(1)
        }}
      >
        <option value="">Todos</option>
        <option value="open">Abiertos</option>
        <option value="overdue">Vencidos</option>
        <option value="closed">Cerrados</option>
      </select>

      {error && <p role="alert">{error}</p>}
      {!result && !error && <p role="status">Cargando historial…</p>}
      {result?.items.length === 0 && <p>No hay préstamos para este filtro.</p>}

      {result && result.items.length > 0 && (
        <table>
          <thead>
            <tr>
              <th scope="col">Socio</th>
              <th scope="col">Fecha del préstamo</th>
              <th scope="col">Estado</th>
              <th scope="col">Acciones</th>
            </tr>
          </thead>
          <tbody>
            {result.items.map((loan) => (
              <tr key={loan.id}>
                <td>
                  {namesById.get(loan.member_id) ??
                    (members === null ? 'Cargando…' : 'Socio no disponible')}
                </td>
                <td>{dateFormat.format(new Date(loan.loaned_at))}</td>
                <td>{statusLabels[loan.status] ?? loan.status}</td>
                <td>
                  <button type="button" onClick={() => setSelectedLoanId(loan.id)}>
                    Ver juegos
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {result && (result.total > result.size || page > 1) && (
        <nav aria-label="Páginas del historial">
          <button type="button" disabled={page === 1} onClick={() => setPage(page - 1)}>
            Anterior
          </button>
          {' '}Página {page}{' '}
          <button
            type="button"
            disabled={page * result.size >= result.total}
            onClick={() => setPage(page + 1)}
          >
            Siguiente
          </button>
        </nav>
      )}

      {selectedLoanId && (
        <LoanReturns
          key={selectedLoanId}
          apiBaseUrl={apiBaseUrl}
          token={token}
          loanId={selectedLoanId}
          revision={revision}
          onReturned={onReturned}
          onClose={() => setSelectedLoanId(null)}
        />
      )}
    </section>
  )
}