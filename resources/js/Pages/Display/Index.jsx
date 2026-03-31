import React, { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import Echo from 'laravel-echo';

export default function Display({ branch }) {
    const [serving, setServing] = useState([]);
    const [waitingCount, setWaitingCount] = useState(0);

    // Función para traer datos iniciales
    const fetchDisplayData = async () => {
        const response = await fetch(`/api/v1/public/branches/${branch.id}/display`);
        const result = await response.json();
        setServing(result.now_serving || []);
        setWaitingCount(result.waiting_count || 0);
    };

    useEffect(() => {
        fetchDisplayData();

        // Escuchar cambios en tiempo real vía WebSockets (Reverb)
        if (window.Echo) {
            window.Echo.channel(`branch.${branch.id}`)
                .listen('TicketCalled', (e) => {
                    fetchDisplayData(); // Refrescar al llamar nuevo turno
                    new Audio('/sounds/notification.mp3').play().catch(() => {});
                });
        }
    }, []);

    return (
        <div className="min-h-screen bg-slate-950 text-white p-8 font-sans">
            <Head title={`Pantalla - ${branch.name}`} />
            
            <header className="flex justify-between items-center mb-12 border-b border-slate-800 pb-6">
                <div>
                    <h1 className="text-4xl font-bold text-blue-500">TurnosPro</h1>
                    <p className="text-slate-400 text-xl">{branch.name}</p>
                </div>
                <div className="text-right">
                    <div className="text-5xl font-mono">{new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
                </div>
            </header>

            <main className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {/* Columna Principal: Turnos siendo llamados */}
                <div className="lg:col-span-2 bg-slate-900 rounded-3xl p-10 border border-slate-800 shadow-2xl">
                    <h2 className="text-2xl text-slate-500 uppercase tracking-widest mb-8">Llamando Ahora</h2>
                    <div className="space-y-6">
                        {serving.map((ticket, index) => (
                            <div key={index} className="flex justify-between items-center bg-slate-800/50 p-8 rounded-2xl border-l-8 border-green-500 animate-pulse">
                                <div className="text-8xl font-black text-white">{ticket.display_number}</div>
                                <div className="text-right">
                                    <div className="text-3xl text-slate-400 uppercase">Ventanilla</div>
                                    <div className="text-7xl font-bold text-green-400">{ticket.counter_number}</div>
                                </div>
                            </div>
                        ))}
                        {serving.length === 0 && (
                            <div className="text-center py-20 text-slate-600 text-3xl italic">Esperando turnos...</div>
                        )}
                    </div>
                </div>

                {/* Columna Lateral: Info de la Cola */}
                <div className="space-y-8">
                    <div className="bg-blue-600 rounded-3xl p-10 text-center shadow-lg">
                        <div className="text-2xl uppercase font-bold mb-2">En Espera</div>
                        <div className="text-9xl font-black">{waitingCount}</div>
                    </div>
                    
                    <div className="bg-slate-900 rounded-3xl p-10 border border-slate-800 h-full">
                        <h3 className="text-xl text-slate-500 mb-6 uppercase">Próximos</h3>
                        <p className="text-slate-400 italic">Prepare su ticket impreso o digital.</p>
                    </div>
                </div>
            </main>
        </div>
    );
}