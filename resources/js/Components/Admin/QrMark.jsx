import { encode } from 'uqr';
import { useEffect, useRef } from 'react';

export default function QrMark({ value, className = '' }) {
    const canvasRef = useRef(null);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas || !value) {
            return;
        }

        const qr = encode(String(value), { ecc: 'M', border: 1 });
        const modules = qr.size;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }

        const scale = 4;
        const px = modules * scale;
        canvas.width = px;
        canvas.height = px;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, px, px);
        ctx.fillStyle = '#111111';

        for (let y = 0; y < modules; y += 1) {
            for (let x = 0; x < modules; x += 1) {
                if (qr.data[y][x]) {
                    ctx.fillRect(x * scale, y * scale, scale, scale);
                }
            }
        }
    }, [value]);

    if (!value) {
        return null;
    }

    return (
        <canvas
            ref={canvasRef}
            className={className}
            aria-hidden
        />
    );
}
