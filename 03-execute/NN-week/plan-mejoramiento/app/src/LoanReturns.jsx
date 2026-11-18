import { useEffect, useState } from 'react'

export default function LoanReturns({
  apiBaseUrl,
  token,
  loanId,
  revision,
  onReturned,
  onClose,
}) {
  const [loan, setLoan] = useState(null)
  const [loadError, setLoadError] = useState('')
  const [returnError, setReturnError] = useState('')
  const [notice, setNotice] = useState('')
  const [returningId, setReturningId] = useState(null)

  const loanUrl = `${apiBaseUrl}/loans/${loanId}`

  useEffect(() => {
    const controller = new AbortController()

    async function loadLoan() {
      try {
        const response = await fetch(loanUrl, {
          headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
          },
          signal: controller.signal,
        })

        if (!response.ok) {
          throw new Error('No se pudieron cargar los juegos del préstamo.')
        }

        const data = await response.json()
        if (!controller.signal.aborted) {
          setLoan(data)
          setLoadError('')
        }
      } catch (error) {
        if (!controller.signal.aborted) setLoadError(error.message)
      }
    }

    loadLoan()
    return () => controller.abort()
  }, [loanUrl, token, revision])

  async function returnGame(itemId) {
    setReturningId(itemId)
    setReturnError('')
    setNotice('')

    try {
      const response = await fetch(`${loanUrl}/items/${itemId}/return`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
      })

      const data = await response.json().catch(() => null)

      if (!response.ok) {
        if (data?.code === 'ITEM_ALREADY_RETURNED') {
          setLoan(null)
          setLoadError('')
          onReturned()
        }

        throw new Error(data?.message || 'No se pudo registrar la devolución.')
      }

      setLoan((current) => ({
        ...current,
        items: current.items.map((item) => (item.id === itemId ? data : item)),
      }))
      setNotice(`Devolución registrada para ${data.game_title_snapshot}.`)
      onReturned()
    } catch (error) {
      setReturnError(error.message)
    } finally {
      setReturningId(null)
    }
  }

  return (
    <section aria-labelledby="loan-returns-title">
      <h4 id="loan-returns-title">Juegos del préstamo</h4>
      <button type="button" onClick={onClose}>Cerrar detalle</button>

      {loadError && <p role="alert">{loadError}</p>}
      {returnError && <p role="alert">{returnError}</p>}
      {notice && <p role="status">{notice}</p>}
      {!loan && !loadError && <p role="status">Cargando juegos…</p>}

      {loan && (
        <ul>
          {loan.items.map((item) => (
            <li key={item.id}>
              {item.game_title_snapshot}{' '}
              {item.returned_at ? (
                <strong>Devuelto</strong>
              ) : (
                <button
                  type="button"
                  aria-label={`Devolver ${item.game_title_snapshot}`}
                  disabled={returningId !== null}
                  onClick={() => returnGame(item.id)}
                >
                  {returningId === item.id ? 'Registrando…' : 'Devolver'}
                </button>
              )}
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}