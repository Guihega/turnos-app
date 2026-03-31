import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function AdminDashboard() {
    return (
        <AuthenticatedLayout header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Administración TurnosPro</h2>}>
            <Head title="Admin - TurnosPro" />
            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div className="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500">
                            <h4 className="font-bold text-slate-500 uppercase text-xs">Sucursales</h4>
                            <p className="text-3xl font-black">3</p>
                        </div>
                        <div className="bg-white p-6 rounded-lg shadow border-l-4 border-purple-500">
                            <h4 className="font-bold text-slate-500 uppercase text-xs">Operadores</h4>
                            <p className="text-3xl font-black">10</p>
                        </div>
                        <div className="bg-white p-6 rounded-lg shadow border-l-4 border-orange-500">
                            <h4 className="font-bold text-slate-500 uppercase text-xs">Servicios</h4>
                            <p className="text-3xl font-black">6</p>
                        </div>
                    </div>
                    <div className="bg-white p-8 rounded-lg shadow italic text-gray-500">
                        Sección de gestión de usuarios y configuraciones globales en desarrollo.[cite: 2]
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}