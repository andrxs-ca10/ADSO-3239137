import { useEffect, useState } from 'react'

const pageSize = 50

async function loadAll(apiBaseUrl, resource, token, signal) {
  const items = []
  let page = 1
  let hasNextPage

  do {
    const response = await fetch(
      `${apiBaseUrl}/${resource}?page=${page}&size=${pageSize}`,
      {
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
        signal,
      },
    )

    if (!response.ok) {
      throw new Error('No se pudieron cargar los socios y juegos para el préstamo.')
    }

    const result = await response.json()
    items.push(...result.items)
    hasNextPage = result.page * result.size < result.total
    page += 1
  } while (hasNextPage)

  return items
}

export default function Loans({ apiBaseUrl, token, revision, onCreated }) {
  const [options, setOptions] = useState(null)
  const [loadError, setLoadError] = useState('')
  const [saveError, setSaveError] = useState('')
  const [saving, setSaving] = useState(false)
  const [createdLoan, setCreatedLoan] = useState(null)

  useEffect(() => {
    const controller = new AbortController()

    Promise.all([
      loadAll(apiBaseUrl, 'members', token, controller.signal),
      loadAll(apiBaseUrl, 'games', token, controller.signal),
    ])
      .then(([members, games]) => {
        if (!controller.signal.aborted) {
          setOptions({ members, games: games.filter((game) => game.available_units > 0) })
          setLoadError('')
        }
      })
      .catch((error) => {
        if (!controller.signal.aborted) setLoadError(error.message)
      })

    return () => controller.abort()
  }, [apiBaseUrl, token, revision])

  async function createLoan(event) {
    event.preventDefault()

    const form = event.currentTarget
    const fields = new FormData(form)
    const gameIds = fields.getAll('game_id')

    setSaveError('')
    setCreatedLoan(null)

    if (gameIds.length < 1 || gameIds.length > 4) {
      setSaveError('Selecciona entre uno y cuatro juegos.')
      return
    }

    setSaving(true)

    try {
      const response = await fetch(`${apiBaseUrl}/loans`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          member_id: fields.get('member_id'),
          items: gameIds.map((gameId) => ({ game_id: gameId })),
        }),
      })

      const data = await response.json().catch(() => null)

      if (!response.ok) {
        throw new Error(data?.message || 'No se pudo registrar el préstamo.')
      }

      form.reset()
      setOptions(null)
      setCreatedLoan(data)
      onCreated()
    } catch (error) {
      setSaveError(error.message)
    } finally {
      setSaving(false)
    }
  }

  return (
    <section aria-labelledby="loans-title">
      <h2 id="loans-title">Préstamos</h2>

      {createdLoan && (
        <p role="status">
          Préstamo registrado. Fecha de devolución{' '}
          {new Date(createdLoan.items[0].due_at).toLocaleDateString('es-CO', {
            timeZone: 'UTC',
          })}
          .
        </p>
      )}
      {loadError && <p role="alert">{loadError}</p>}
      {!options && !loadError && <p role="status">Cargando socios y juegos…</p>}

      {options && (
        <form onSubmit={createLoan}>
          <fieldset disabled={saving}>
            <legend>Registrar préstamo</legend>

            <p>
              <label htmlFor="loan-member">Socio</label>{' '}
              <select id="loan-member" name="member_id" defaultValue="" required>
                <option value="" disabled>Selecciona un socio</option>
                {options.members.map((member) => (
                  <option key={member.id} value={member.id}>
                    {member.full_name} · {member.document_number}
                  </option>
                ))}
              </select>
            </p>

            <p>
              <label htmlFor="loan-games">Juegos disponibles (entre 1 y 4)</label>
              <br />
              <select id="loan-games" name="game_id" multiple size={6} required>
                {options.games.map((game) => (
                  <option key={game.id} value={game.id}>
                    {game.title} · {game.available_units} disponibles
                  </option>
                ))}
              </select>
              <br />
              <small>En Windows, mantén Ctrl para seleccionar varios juegos.</small>
            </p>

            <button type="submit" disabled={!options.members.length || !options.games.length}>
              {saving ? 'Registrando…' : 'Registrar préstamo'}
            </button>
          </fieldset>
          {saveError && <p role="alert">{saveError}</p>}
        </form>
      )}
    </section>
  )
}