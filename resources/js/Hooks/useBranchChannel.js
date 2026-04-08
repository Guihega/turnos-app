// resources/js/hooks/useBranchChannel.js
import { useEffect, useRef } from 'react';

/**
 * Hook para escuchar eventos de broadcast en un canal de branch.
 *
 * @param {string} branchId - ID de la branch
 * @param {string} channelType - 'display' (público) o 'branch' (privado para operadores)
 * @param {Object} listeners - { 'EventName': (data) => void }
 *
 * Ejemplo de uso:
 *   useBranchChannel(branch.id, 'display', {
 *       'TicketCalled': (data) => console.log('Llamaron:', data.display_number),
 *       'TicketIssued': (data) => console.log('Nuevo turno:', data.display_number),
 *   });
 */
export function useBranchChannel(branchId, channelType, listeners) {
    const listenersRef = useRef(listeners);
    listenersRef.current = listeners;

    useEffect(() => {
        if (!branchId || !window.Echo) return;

        const channelName = `${channelType}.${branchId}`;
        const channel = channelType === 'display'
            ? window.Echo.channel(channelName)       // público
            : window.Echo.private(channelName);       // privado (requiere auth)

        const eventNames = Object.keys(listenersRef.current);

        eventNames.forEach(eventName => {
            // Laravel broadcast event names: .App\Events\TicketCalled
            const fullEventName = `.App\\Events\\${eventName}`;
            channel.listen(fullEventName, (data) => {
                if (listenersRef.current[eventName]) {
                    listenersRef.current[eventName](data);
                }
            });
        });

        return () => {
            window.Echo.leave(channelName);
        };
    }, [branchId, channelType]);
}

/**
 * Hook para verificar si Echo/WebSocket está conectado.
 * Retorna true si hay conexión activa.
 */
export function useEchoConnected() {
    const connected = useRef(false);

    useEffect(() => {
        if (window.Echo?.connector?.pusher?.connection?.state === 'connected') {
            connected.current = true;
        }

        const checkConnection = () => {
            connected.current = window.Echo?.connector?.pusher?.connection?.state === 'connected';
        };

        const interval = setInterval(checkConnection, 3000);
        return () => clearInterval(interval);
    }, []);

    return connected;
}
