import Icon from '@/components/wrappers/Icon'
import type { Feature, FeatureCollection } from 'geojson'
import type { GeoJSONSource, Map as MapLibreMap, MapMouseEvent, Marker } from 'maplibre-gl'
// MapLibre 6 looks for its worker beside its own bundle, a file Vite never
// emits — without it no vector tile (or the fence's GeoJSON) is ever drawn.
// Vite builds the worker with its imports and hands back its URL.
import maplibreWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'
import { useEffect, useRef, useState } from 'react'

/**
 * Interactive geofence editor for the property form (React-controlled, ADR-0027).
 * MapLibre GL over OpenFreeMap's vector "Liberty" style + Nominatim geocoding —
 * no API token or account needed. Drag the pin or click the map to set
 * coordinates; the radius circle and its "300 m" tag redraw live; an
 * auto-dropped starting pin (geocoded from the property's address when no
 * coordinates exist) and a "Use property address" button (re-geocodes after the
 * address changes) cover the common cases. The plain lat/lng inputs stay
 * authoritative — this card reads and writes them.
 */

type SearchResult = { display_name: string; lat: string; lon: string }

const round7 = (n: number) => Math.round(n * 1e7) / 1e7

const degPerMeterLng = (lat: number) => 1 / Math.max(111320 * Math.cos((lat * Math.PI) / 180), 1)

/** ~64-point circle polygon around a lat/lng, radius in meters. */
const circleFeature = (lat: number, lng: number, radiusM: number): Feature => {
  const points: [number, number][] = []
  const degPerMeterLat = 1 / 110574
  for (let i = 0; i <= 64; i++) {
    const angle = (i / 64) * 2 * Math.PI
    points.push([lng + Math.cos(angle) * radiusM * degPerMeterLng(lat), lat + Math.sin(angle) * radiusM * degPerMeterLat])
  }
  return { type: 'Feature', properties: {}, geometry: { type: 'Polygon', coordinates: [points] } }
}

// Free vector tiles, no key: a clean street map in the spirit of Mapbox Streets
// (the legacy app's), rather than the busy standard OpenStreetMap raster.
const MAP_STYLE = 'https://tiles.openfreemap.org/styles/liberty'

// The brand gold the legacy geofence map used, for the pin and the fence.
const FENCE_COLOR = '#CCAB5C'

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
  const badgeRef = useRef<Marker | null>(null)
  // The circle's source exists only after the map's load event; until then
  // there is nothing to draw into.
  const fenceReady = useRef(false)
  const [mapFailed, setMapFailed] = useState(false)
  const [locating, setLocating] = useState(false)
  const [locateError, setLocateError] = useState<string | null>(null)

  // Latest values for use inside map callbacks without re-initializing the map.
  const stateRef = useRef({ latitude, longitude, radius, addressQuery, onCoordinates })
  stateRef.current = { latitude, longitude, radius, addressQuery, onCoordinates }

  const parsed = () => {
    const lat = parseFloat(stateRef.current.latitude)
    const lng = parseFloat(stateRef.current.longitude)
    return isNaN(lat) || isNaN(lng) ? null : { lat, lng }
  }

  // Not map.isStyleLoaded(): it stays false while any source is still loading,
  // including the circle's own just-added one, so the circle was skipped on
  // first load and only appeared once the pin or radius changed.
  const syncCircle = () => {
    const map = mapRef.current
    if (!map || !fenceReady.current) return
    const point = parsed()
    const radiusM = stateRef.current.radius || 0
    const feature: FeatureCollection = {
      type: 'FeatureCollection',
      features: point ? [circleFeature(point.lat, point.lng, radiusM)] : [],
    }
    const source = map.getSource('geofence') as GeoJSONSource | undefined
    source?.setData(feature)

    // The radius tag sits on the circle's east edge.
    const badge = badgeRef.current
    if (!badge) return
    if (point && radiusM > 0) {
      badge.getElement().textContent = `${radiusM} m`
      badge.setLngLat([point.lng + radiusM * degPerMeterLng(point.lat), point.lat]).addTo(map)
    } else {
      badge.remove()
    }
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
        maplibregl.setWorkerUrl(maplibreWorkerUrl)

        const start = parsed()
        const map = new maplibregl.Map({
          container: containerRef.current,
          style: MAP_STYLE,
          center: start ? [start.lng, start.lat] : [-97.5, 35.5], // continental US
          zoom: start ? 15 : 3,
          attributionControl: { compact: true },
        })
        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right')
        // Only a map that never loaded falls back to the fields; a tile that
        // fails later shouldn't take the whole map away.
        let loaded = false
        map.on('error', () => {
          if (!loaded) setMapFailed(true)
        })

        const marker = new maplibregl.Marker({ draggable: true, color: FENCE_COLOR })
        marker.on('dragend', () => {
          const pos = marker.getLngLat()
          setCoordinates(pos.lat, pos.lng, false)
        })
        map.on('click', (e: MapMouseEvent) => setCoordinates(e.lngLat.lat, e.lngLat.lng, false))

        const badge = document.createElement('div')
        badge.className =
          'pointer-events-none whitespace-nowrap rounded-full border border-default-200 bg-card px-2.5 py-0.5 text-xs font-bold text-[#8B6F2F] shadow-md'

        mapRef.current = map
        markerRef.current = marker
        badgeRef.current = new maplibregl.Marker({ element: badge })

        map.on('load', () => {
          loaded = true
          // Flat buildings, like the legacy map: Liberty raises them in 3D from
          // zoom 14, where its flat building layer stops — so hide the 3D one
          // and let the flat one carry on all the way in.
          if (map.getLayer('building-3d')) {
            map.setLayoutProperty('building-3d', 'visibility', 'none')
            map.setLayerZoomRange('building', 13, 24)
          }
          map.addSource('geofence', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } })
          map.addLayer({ id: 'geofence-fill', type: 'fill', source: 'geofence', paint: { 'fill-color': FENCE_COLOR, 'fill-opacity': 0.2 } })
          map.addLayer({ id: 'geofence-line', type: 'line', source: 'geofence', paint: { 'line-color': FENCE_COLOR, 'line-width': 2 } })
          fenceReady.current = true

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
      badgeRef.current = null
      fenceReady.current = false
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

  // The pin only follows the address on its own when there are no coordinates
  // yet; after an address change, this moves it (and the coordinates) there.
  const locateFromAddress = async () => {
    const q = addressQuery.trim()
    setLocateError(null)
    setLocating(true)
    try {
      const found = await geocode(q, 1)
      if (found.length === 0) {
        setLocateError(`Couldn't find "${q}". Drag the pin or click the map instead.`)
        return
      }
      setCoordinates(parseFloat(found[0].lat), parseFloat(found[0].lon), true)
    } catch {
      setLocateError('The address lookup is unavailable right now. Drag the pin or enter the coordinates instead.')
    } finally {
      setLocating(false)
    }
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
      <div ref={containerRef} className="border-default-200 h-120 w-full overflow-hidden rounded-lg border" />

      <div className="flex flex-wrap items-start justify-between gap-2">
        <p className="text-default-400 text-xs">
          Drag the pin or click the map to set the location. The circle shows the geofence — contractors can only QR clock-in inside it.
        </p>
        <button
          type="button"
          className="btn btn-light px-3 py-1.5 text-sm"
          disabled={locating || addressQuery.trim().length < 3}
          title={addressQuery.trim().length < 3 ? 'Enter the address first' : `Look up ${addressQuery}`}
          onClick={locateFromAddress}
        >
          <Icon icon="map-pin" className="size-4" />
          {locating ? 'Locating…' : 'Use property address'}
        </button>
      </div>
      {locateError && <p className="text-danger text-xs">{locateError}</p>}
    </div>
  )
}

export default GeofenceMapCard
