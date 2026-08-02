import { Crosshair, MapPin, Trash2 } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';

const DEFAULT_CENTER = [-2.5489, 118.0149];
const DEFAULT_ZOOM = 5;
const SELECTED_ZOOM = 16;

const fieldClass =
    'mt-1.5 w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal';

function toNumberOrNull(value) {
    if (value === '' || value === null || value === undefined) return null;
    const n = Number(value);
    return Number.isFinite(n) ? n : null;
}

function formatCoord(value) {
    if (value === null || value === undefined || value === '') return '';
    const n = Number(value);
    return Number.isFinite(n) ? String(n) : '';
}

export default function GpsMapPicker({
    latitude,
    longitude,
    onChange,
    errors = {},
}) {
    const mapId = useId().replace(/:/g, '');
    const mapRef = useRef(null);
    const markerRef = useRef(null);
    const leafletRef = useRef(null);
    const onChangeRef = useRef(onChange);
    const [geoError, setGeoError] = useState('');
    const [locating, setLocating] = useState(false);

    const lat = toNumberOrNull(latitude);
    const lng = toNumberOrNull(longitude);
    const hasPoint = lat !== null && lng !== null;

    useEffect(() => {
        onChangeRef.current = onChange;
    }, [onChange]);

    useEffect(() => {
        let cancelled = false;

        (async () => {
            const leafletModule = await import('leaflet');
            const L = leafletModule.default || leafletModule;
            if (cancelled) return;

            leafletRef.current = L;

            delete L.Icon.Default.prototype._getIconUrl;
            L.Icon.Default.mergeOptions({
                iconRetinaUrl: markerIcon2x,
                iconUrl: markerIcon,
                shadowUrl: markerShadow,
            });

            if (mapRef.current) return;

            const initialLat = toNumberOrNull(latitude);
            const initialLng = toNumberOrNull(longitude);
            const hasInitial = initialLat !== null && initialLng !== null;
            const center = hasInitial ? [initialLat, initialLng] : DEFAULT_CENTER;
            const zoom = hasInitial ? SELECTED_ZOOM : DEFAULT_ZOOM;

            const map = L.map(mapId, {
                center,
                zoom,
                scrollWheelZoom: true,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(map);

            map.on('click', (event) => {
                onChangeRef.current({
                    latitude: Number(event.latlng.lat.toFixed(7)),
                    longitude: Number(event.latlng.lng.toFixed(7)),
                });
            });

            mapRef.current = map;

            if (hasInitial) {
                markerRef.current = L.marker([initialLat, initialLng], {
                    draggable: true,
                }).addTo(map);
                markerRef.current.on('dragend', (event) => {
                    const position = event.target.getLatLng();
                    onChangeRef.current({
                        latitude: Number(position.lat.toFixed(7)),
                        longitude: Number(position.lng.toFixed(7)),
                    });
                });
            }

            window.setTimeout(() => map.invalidateSize(), 80);
        })();

        return () => {
            cancelled = true;
            if (mapRef.current) {
                mapRef.current.remove();
                mapRef.current = null;
                markerRef.current = null;
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [mapId]);

    useEffect(() => {
        const map = mapRef.current;
        const L = leafletRef.current;
        if (!map || !L) return;

        if (!hasPoint) {
            if (markerRef.current) {
                map.removeLayer(markerRef.current);
                markerRef.current = null;
            }
            return;
        }

        if (!markerRef.current) {
            markerRef.current = L.marker([lat, lng], { draggable: true }).addTo(map);
            markerRef.current.on('dragend', (event) => {
                const position = event.target.getLatLng();
                onChangeRef.current({
                    latitude: Number(position.lat.toFixed(7)),
                    longitude: Number(position.lng.toFixed(7)),
                });
            });
        } else {
            const current = markerRef.current.getLatLng();
            if (
                Math.abs(current.lat - lat) > 0.0000001 ||
                Math.abs(current.lng - lng) > 0.0000001
            ) {
                markerRef.current.setLatLng([lat, lng]);
            }
        }

        const currentZoom = map.getZoom();
        map.setView([lat, lng], Math.max(currentZoom, SELECTED_ZOOM - 2), {
            animate: true,
        });
    }, [hasPoint, lat, lng]);

    const useMyLocation = () => {
        if (!navigator.geolocation) {
            setGeoError('Peramban tidak mendukung geolocation.');
            return;
        }

        setLocating(true);
        setGeoError('');
        navigator.geolocation.getCurrentPosition(
            (position) => {
                onChangeRef.current({
                    latitude: Number(position.coords.latitude.toFixed(7)),
                    longitude: Number(position.coords.longitude.toFixed(7)),
                });
                setLocating(false);
            },
            (error) => {
                setLocating(false);
                setGeoError(
                    error.code === error.PERMISSION_DENIED
                        ? 'Izin lokasi ditolak. Izinkan akses lokasi di peramban.'
                        : 'Gagal mengambil lokasi perangkat.',
                );
            },
            { enableHighAccuracy: true, timeout: 15000 },
        );
    };

    const clearPoint = () => {
        onChange({ latitude: '', longitude: '' });
        setGeoError('');
    };

    return (
        <div className="space-y-3 border border-ink/10 bg-mist/20 p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="inline-flex items-center gap-2 text-sm font-semibold text-ink">
                        <MapPin className="h-4 w-4 text-signal-deep" />
                        Titik GPS
                    </p>
                    <p className="mt-1 text-xs text-ink-soft">
                        Klik peta atau geser penanda untuk menentukan posisi alamat pelanggan.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={useMyLocation}
                        disabled={locating}
                        className="btn-action btn-action-xs btn-secondary"
                    >
                        <Crosshair className="h-3.5 w-3.5" />
                        {locating ? 'Mengambil...' : 'Lokasi saya'}
                    </button>
                    {hasPoint && (
                        <button
                            type="button"
                            onClick={clearPoint}
                            className="btn-action btn-action-xs btn-danger"
                        >
                            <Trash2 className="h-3.5 w-3.5" />
                            Hapus titik
                        </button>
                    )}
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <label className="block text-sm font-medium text-ink">
                    Latitude
                    <input
                        type="number"
                        step="any"
                        inputMode="decimal"
                        value={formatCoord(latitude)}
                        onChange={(e) =>
                            onChange({
                                latitude: e.target.value,
                                longitude: longitude ?? '',
                            })
                        }
                        placeholder="-6.2000000"
                        className={fieldClass}
                    />
                    {errors.latitude && (
                        <span className="mt-1 block text-xs text-red-600">{errors.latitude}</span>
                    )}
                </label>
                <label className="block text-sm font-medium text-ink">
                    Longitude
                    <input
                        type="number"
                        step="any"
                        inputMode="decimal"
                        value={formatCoord(longitude)}
                        onChange={(e) =>
                            onChange({
                                latitude: latitude ?? '',
                                longitude: e.target.value,
                            })
                        }
                        placeholder="106.8166660"
                        className={fieldClass}
                    />
                    {errors.longitude && (
                        <span className="mt-1 block text-xs text-red-600">{errors.longitude}</span>
                    )}
                </label>
            </div>

            <div
                id={mapId}
                className="h-64 w-full border border-ink/10 bg-mist sm:h-72"
            />

            {hasPoint ? (
                <p className="text-xs text-ink-soft">
                    Titik tersimpan:{' '}
                    <span className="font-semibold text-ink">
                        {lat}, {lng}
                    </span>
                    {' · '}
                    <a
                        href={`https://www.google.com/maps?q=${lat},${lng}`}
                        target="_blank"
                        rel="noreferrer"
                        className="font-semibold text-signal-deep hover:text-ink"
                    >
                        Buka di Google Maps
                    </a>
                </p>
            ) : (
                <p className="text-xs text-ink-soft">Belum ada titik GPS dipilih.</p>
            )}

            {geoError && <p className="text-xs text-amber-700">{geoError}</p>}
        </div>
    );
}
