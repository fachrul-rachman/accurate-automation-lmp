import { useEffect, useState } from 'react';

export function useOnlineStatus() {
    const [online, setOnline] = useState(() =>
        typeof navigator === 'undefined' ? true : navigator.onLine,
    );

    useEffect(() => {
        function updateOnline() {
            setOnline(navigator.onLine);
        }

        window.addEventListener('online', updateOnline);
        window.addEventListener('offline', updateOnline);

        return () => {
            window.removeEventListener('online', updateOnline);
            window.removeEventListener('offline', updateOnline);
        };
    }, []);

    return online;
}
