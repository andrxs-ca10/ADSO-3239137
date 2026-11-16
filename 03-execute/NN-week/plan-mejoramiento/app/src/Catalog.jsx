import { useEffect, useState } from 'react'

const pageSize = 20

export default function Catalog({ apiBaseUrl, token }) {
  const [page, setPage] = useState(1)
  const [result, setResult] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    const controller = new AbortController()

    async function loadGames() {
      try {
        const response = await fetch(
          `${apiBaseUrl}/games?page=${page}&size=${pageSize}`,
          {
            headers: {
              Accept: 'application/json',
              Authorization: `Bearer ${token}`,
            },
            signal: controller.signal,
          },
        )

        if (response.status === 401) {
          throw new Error('La sesión ya no es válida. Cierra sesión y vuelve a ingresar.')
        }

        if (!response.ok) {
          throw new Error('No se pudo cargar el catálogo.')
        }

        const data = await response.json()
        if (!controller.signal.aborted) setResult(data)
      } catch (requestError) {
        if (!controller.signal.aborted) setError(requestError.message)
      }
    }

    loadGames()

    return () => controller.abort()
  }, [apiBaseUrl, token, page])

  function changePage(nextPage) {
    setResult(null)
    setError('')
    setPage(nextPage)
  }

  return (
    <section aria-labelledby="catalog-title">
      <h2 id="catalog-title">Catálogo de juegos</h2>

      {error && <p role="alert">{error}</p>}
      {!result && !error && <p role="status">Cargando catálogo…</p>}

      {result && (
        <>
          <p>Total de juegos: {result.total}</p>

          {result.items.length === 0 ? (
            <p>No hay juegos para mostrar.</p>
          ) : (
            <div style={{ overflowX: 'auto' }}>
              <table>
                <thead>
                  <tr>
                    <th scope="col">Título</th>
                    <th scope="col">Categoría</th>
                    <th scope="col">Disponibles</th>
                  </tr>
                </thead>
                <tbody>
                  {result.items.map((game) => (
                    <tr key={game.id}>
                      <td>{game.title}</td>
                      <td>{game.category.name}</td>
                      <td>{game.available_units} de {game.total_units}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {result.total > result.size && (
            <nav aria-label="Páginas del catálogo">
              <button
                type="button"
                disabled={page === 1}
                onClick={() => changePage(page - 1)}
              >
                Anterior
              </button>
              {' '}Página {page} de {Math.ceil(result.total / result.size)}{' '}
              <button
                type="button"
                disabled={page * result.size >= result.total}
                onClick={() => changePage(page + 1)}
              >
                Siguiente
              </button>
            </nav>
          )}
        </>
      )}
    </section>
  )
}