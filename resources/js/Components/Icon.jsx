import {
    Headphones,
    Lock,
    Radar,
    Router,
    Shield,
    Signal,
    Wifi,
    Zap,
} from 'lucide-react';

const icons = {
    wifi: Wifi,
    signal: Signal,
    router: Router,
    shield: Shield,
    zap: Zap,
    radar: Radar,
    lock: Lock,
    headphones: Headphones,
};

export default function Icon({ name, className = 'h-5 w-5' }) {
    const Comp = icons[name] || Wifi;
    return <Comp className={className} strokeWidth={1.75} aria-hidden />;
}
