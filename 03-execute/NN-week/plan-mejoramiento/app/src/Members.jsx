import { useEffect, useState } from 'react'

const pageSize = 20

export default function Members({ apiBaseUrl, token }) {
  const [search, setSearch] = useState('')
  const [filters, setFilters] = useState({ query: '', page: 1 })
  const [result, setResult] = useState(null)
  const [listError, setListError] = useState('')
  const [saving, setSaving] = useState(false)
  const [saveError, setSaveError] = useState('')
  const [notice, setNotice] = useState('')

  useEffect(() => {
    const controller = new AbortController()
    const params = new URLSearchParams({
      q: filters.query,
      page: String(filters.page),
      size: String(pageSize),
    })

    async function loadMembers() {
      try {
        const response = await fetch(`${apiBaseUrl}/members?${params}`, {
          headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
          },
          signal: controller.signal,
        })

        if (!response.ok) {
          throw new Error('No se pudo cargar la lista de socios.')
        }

        const data = await response.json()
        if (!controller.signal.aborted) setResult(data)
      } catch (error) {
        if (!controller.signal.aborted) setListError(error.message)
      }
    }

    loadMembers()
    return () => controller.abort()
  }, [apiBaseUrl, token, filters])

  function loadPage(query, page = 1) {
    setResult(null)
    setListError('')
    setFilters({ query, page })
  }

  async function registerMember(event) {
    event.preventDefault()

    const form = event.currentTarget
    const fields = new FormData(form)
    const fullName = fields.get('full_name').trim()
    const documentNumber = fields.get('document_number').trim()

    setSaveError('')
    setNotice('')

    if (!fullName || !documentNumber) {
      setSaveError('Escribe el nombre y el número de documento.')
      return
    }

    setSaving(true)

    try {
      const response = await fetch(`${apiBaseUrl}/members`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          full_name: fullName,
          document_number: documentNumber,
        }),
      })

      if (!response.ok) {
        const data = await response.json().catch(() => null)
        throw new Error(data?.message || 'No se pudo registrar al socio.')
      }

      form.reset()
      setNotice(`Socio registrado: ${fullName}.`)
      setSearch(documentNumber)
      loadPage(documentNumber)
    } catch (error) {
      setSaveError(error.message)
    } finally {
      setSaving(false)
    }
  }

  return (
    <section aria-labelledby="members-title">
      <h2 id="members-title">Socios</h2>

      <form onSubmit={(event) => {
        event.preventDefault()
        loadPage(search.trim())
      }}>
        <label htmlFor="member-search">Nombre o documento</label>{' '}
        <input
          id="member-search"
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          maxLength={255}
        />{' '}
        <button type="submit">Buscar</button>{' '}
        <button type="button" onClick={() => {
          setSearch('')
          loadPage('')
        }}>
          Ver todos
        </button>
      </form>

      {listError && <p role="alert">{listError}</p>}
      {!result && !listError && <p role="status">Cargando socios…</p>}

      {result && (
        <>
          <p>Socios encontrados: {result.total}</p>
          {result.items.length === 0 ? (
            <p>No se encontraron socios.</p>
          ) : (
            <ul>
              {result.items.map((member) => (
                <li key={member.id}>
                  {member.full_name} · {member.document_number}
                </li>
              ))}
            </ul>
          )}

          {result.total > result.size && (
            <nav aria-label="Páginas de socios">
              <button
                type="button"
                disabled={result.page === 1}
                onClick={() => loadPage(filters.query, result.page - 1)}
              >
                Anterior
              </button>
              {' '}Página {result.page} de {Math.ceil(result.total / result.size)}{' '}
              <button
                type="button"
                disabled={result.page * result.size >= result.total}
                onClick={() => loadPage(filters.query, result.page + 1)}
              >
                Siguiente
              </button>
            </nav>
          )}
        </>
      )}

      <h3>Registrar socio</h3>
      <form onSubmit={registerMember}>
        <p>
          <label htmlFor="member-name">Nombre completo</label><br />
          <input id="member-name" name="full_name" maxLength={255} required />
        </p>
        <p>
          <label htmlFor="member-document">Número de documento</label><br />
          <input id="member-document" name="document_number" maxLength={255} required />
        </p>
        <button type="submit" disabled={saving}>
          {saving ? 'Registrando…' : 'Registrar socio'}
        </button>
        {saveError && <p role="alert">{saveError}</p>}
        {notice && <p role="status">{notice}</p>}
      </form>
    </section>
  )
}