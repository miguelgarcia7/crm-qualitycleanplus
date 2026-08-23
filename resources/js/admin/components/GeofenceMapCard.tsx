import Icon from '@/components/wrappers/Icon'
import type { Feature, FeatureCollection } from 'geojson'
import type { GeoJSONSource, Map as MapLibreMap, MapMouseEvent, Marker } from 'maplibre-gl'
import { useEffect, useRef, useState } from 'react'

/**
 * Interactive geofence editor for the property form (React-controlled, ADR-0027).
 * MapLibre GL over OSM raster tiles + Nominatim geocoding — no API token needed.
 * Drag the pin or click the map to set coordinates; the radius circle redraws
 * live; an address search and an auto-dropped starting pin (geocoded from the
 * property's address when no coordinates exist) cover the common cases. The
 * plain lat/lng inputs stay authoritative — this card reads and writes them.
 */

type SearchResult = { display_name: string; lat: string; lon: string }

const round7 = (n: number) => Math.round(n * 1e7) / 1e7

/** ~64-point circle polygon around a lat/lng, radius in meters. */
const circleFeature = (lat: number, lng: number, radiusM: number): Feature => {
  const points: [number, number][] = []
  const degPerMeterLat = 1 / 110574
  const degPerMeterLng = 1 / Math.max(111320 * Math.cos((lat * Math.PI) / 180), 1)
  for (let i = 0; i <= 64; i++) {
    const angle = (i / 64) * 2 * Math.PI
    points.push([lng + Math.cos(angle) * radiusM * degPerMeterLng, lat + Math.sin(angle) * radiusM * degPerMeterLat])
  }
  return { type: 'Feature', properties: {}, geometry: { type: 'Polygon', coordinates: [points] } }
}

const OSM_STYLE = {
  version: 8 as const,
  sources: {
    osm: {
      type: 'raster' as const,
      tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
      tileSize: 256,
      attribution: '© OpenStreetMap contributors',
    },
  },
  layers: [{ id: 'osm', type: 'raster' as const, source: 'osm' }],
}

const GeofenceMapCard = ({
  latitude,
  longitude,
  radius,
  addressQuery,
  onCoordinates,
}: {
  latitude: string
  longitude: string
  radius: number
  addressQuery: string
  onCoordinates: (lat: string, lng: string) => void
}) => {
  const containerRef = useRef<HTMLDivElement>(null)
  const mapRef = useRef<MapLibreMap | null>(null)
  const markerRef = useRef<Marker | null>(null)
  const [mapFailed, setMapFailed] = useState(false)
  const [query, setQuery] = useState('')
  const [results, setResults] = useState<SearchResult[]>([])
  const [searching, setSearching] = useState(false)
  const searchSeq = useRef(0)
  const debounceTimer = useRef<ReturnType<typeof setTimeout>>(null)

  // Latest values for use inside map callbacks without re-initializing the map.
  const stateRef = useRef({ latitude, longitude, radius, addressQuery, onCoordinates })
  stateRef.current = { latitude, longitude, radius, addressQuery, onCoordinates }

  const parsed = () => {
    const lat = parseFloat(stateRef.current.latitude)
    const lng = parseFloat(stateRef.current.longitude)
    return isNaN(lat) || isNaN(lng) ? null : { lat, lng }
  }

  const syncCircle = () => {
    const map = mapRef.current
    if (!map || !map.isStyleLoaded()) return
    const point = parsed()
    const feature: FeatureCollection = {
      type: 'FeatureCollection',
      features: point ? [circleFeature(point.lat, point.lng, stateRef.current.radius || 0)] : [],
    }
    const source = map.getSource('geofence') as GeoJSONSource | undefined
    if (source) source.setData(feature)
  }

  const placePin = (lat: number, lng: number, recenter: boolean) => {
    const map = mapRef.current
    const marker = markerRef.current
    if (!map || !marker) return
    marker.setLngLat([lng, lat]).addTo(map)
    if (recenter) map.easeTo({ center: [lng, lat], zoom: Math.max(map.getZoom(), 14) })
    syncCircle()
  }

  const setCoordinates = (lat: number, lng: number, recenter: boolean) => {
    stateRef.current.onCoordinates(String(round7(lat)), String(round7(lng)))
    placePin(lat, lng, recenter)
  }

  // Init once.
  useEffect(() => {
    let disposed = false

    const init = async () => {
      try {
        const maplibregl = await import('maplibre-gl')
        await import('maplibre-gl/dist/maplibre-gl.css')
        if (disposed || !containerRef.current) return

        const start = parsed()
        const map = new maplibregl.Map({
          container: containerRef.current,
          style: OSM_STYLE,
          center: start ? [start.lng, start.lat] : [-97.5, 35.5], // continental US
          zoom: start ? 15 : 3,
          attributionControl: { compact: true },
        })
        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right')
        map.on('error', () => setMapFailed(true))

        const marker = new maplibregl.Marker({ draggable: true, color: '#d97706' })
        marker.on('dragend', () => {
          const pos = marker.getLngLat()
          setCoordinates(pos.lat, pos.lng, false)
        })
        map.on('click', (e: MapMouseEvent) => setCoordinates(e.lngLat.lat, e.lngLat.lng, false))

        mapRef.current = map
        markerRef.current = marker

        map.on('load', () => {
          map.addSource('geofence', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } })
          map.addLayer({ id: 'geofence-fill', type: 'fill', source: 'geofence', paint: { 'fill-color': '#d97706', 'fill-opacity': 0.15 } })
          map.addLayer({ id: 'geofence-line', type: 'line', source: 'geofence', paint: { 'line-color': '#d97706', 'line-width': 2 } })

          const point = parsed()
          if (point) {
            placePin(point.lat, point.lng, false)
          } else {
            autoPlaceFromAddress()
          }
        })
      } catch {
        setMapFailed(true)
      }
    }

    // Geocode the property's address as a starting pin when none is set yet.
    const autoPlaceFromAddress = async () => {
      const q = stateRef.current.addressQuery.trim()
      if (q.length < 3) return
      try {
        const found = await geocode(q, 1)
        if (disposed || parsed() !== null || found.length === 0) return
        setCoordinates(parseFloat(found[0].lat), parseFloat(found[0].lon), true)
      } catch {
        // best-effort only
      }
    }

    init()

    return () => {
      disposed = true
      mapRef.current?.remove()
      mapRef.current = null
      markerRef.current = null
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // Typing coordinates (or clearing them) moves the pin / circle.
  useEffect(() => {
    const point = parsed()
    if (point) {
      placePin(point.lat, point.lng, true)
    } else {
      markerRef.current?.remove()
      syncCircle()
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [latitude, longitude])

  // Radius changes redraw the circle.
  useEffect(() => {
    syncCircle()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [radius])

  const geocode = async (q: string, limit: number): Promise<SearchResult[]> => {
    const params = new URLSearchParams({ format: 'jsonv2', limit: String(limit), q })
    const response = await fetch(`https://nominatim.openstreetmap.org/search?${params}`, {
      headers: { Accept: 'application/json' },
    })
    if (!response.ok) throw new Error('geocode failed')
    return (await response.json()) as SearchResult[]
  }

  const onSearchInput = (value: string) => {
    setQuery(value)
    if (debounceTimer.current) clearTimeout(debounceTimer.current)
    if (value.trim().length < 3) {
      setResults([])
      return
    }
    debounceTimer.current = setTimeout(async () => {
      const seq = ++searchSeq.current
      setSearching(true)
      try {
        const found = await geocode(value, 5)
        if (seq === searchSeq.current) setResults(found)
      } catch {
        if (seq === searchSeq.current) setResults([])
      } finally {
        if (seq === searchSeq.current) setSearching(false)
      }
    }, 400)
  }

  const selectResult = (result: SearchResult) => {
    setResults([])
    setQuery('')
    setCoordinates(parseFloat(result.lat), parseFloat(result.lon), true)
  }

  if (mapFailed) {
    return (
      <p className="text-default-400 text-sm">
        Map preview is unavailable — enter the coordinates manually below (right-click a location in Google Maps to copy them).
      </p>
    )
  }

  return (
    <div className="space-y-2">
      <div className="relative">
        <div ref={containerRef} className="border-default-200 h-80 w-full overflow-hidden rounded-lg border" />

        <div className="absolute start-2 top-2 w-72 max-w-[calc(100%-1rem)]">
          <div className="input-icon-group">
            <Icon icon="search" className="input-icon" />
            <input
              className="form-input bg-card w-full shadow"
              placeholder="Search address…"
              value={query}
              onChange={(e) => onSearchInput(e.target.value)}
            />
          </div>
          {(results.length > 0 || searching) && (
            <div className="border-default-200 bg-card mt-1 max-h-52 overflow-y-auto rounded-lg border shadow-lg">
              {searching && <p className="text-default-400 px-3 py-2 text-sm">Searching…</p>}
              {results.map((r, i) => (
                <button
                  key={i}
                  type="button"
                  className="hover:bg-light/60 block w-full px-3 py-2 text-start text-sm"
                  onClick={() => selectResult(r)}
                >
                  {r.display_name}
                </button>
              ))}
            </div>
          )}
        </div>
      </div>
      <p className="text-default-400 text-xs">
        Drag the pin or click the map to set the location. The circle shows the geofence — contractors can only QR clock-in inside it.
      </p>
    </div>
  )
}

export default GeofenceMapCard
