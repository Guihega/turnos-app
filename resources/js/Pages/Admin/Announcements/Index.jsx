// resources/js/Pages/Admin/Announcements/Index.jsx
import { useState } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const V = (n) => `var(${n})`;

const TYPES = [
    { value: 'announcement', label: '📢 Anuncio', desc: 'Avisos generales para la pantalla' },
    { value: 'news', label: '📰 Noticia', desc: 'Información de interés para los clientes' },
    { value: 'promo', label: '🎉 Promoción', desc: 'Ofertas y promociones' },
];

const TYPE_COLORS = {
    announcement: { bg: 'var(--t-blue)', light: 'color-mix(in srgb, var(--t-blue) 10%, transparent)' },
    news: { bg: 'var(--t-purple)', light: 'color-mix(in srgb, var(--t-purple) 10%, transparent)' },
    promo: { bg: 'var(--t-green)', light: 'color-mix(in srgb, var(--t-green) 10%, transparent)' },
};

const TYPE_LABELS = { announcement: 'Anuncio', news: 'Noticia', promo: 'Promoción' };

export default function AnnouncementsIndex({ announcements, branches }) {
    const { flash } = usePage().props;
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);

    const form = useForm({
        type: 'announcement',
        title: '',
        body: '',
        branch_id: '',
        priority: 0,
        is_active: true,
        starts_at: '',
        ends_at: '',
    });

    const resetForm = () => {
        form.reset();
        setEditing(null);
        setShowForm(false);
    };

    const startEdit = (item) => {
        setEditing(item);
        form.setData({
            type: item.type,
            title: item.title,
            body: item.body || '',
            branch_id: item.branch_id || '',
            priority: item.priority || 0,
            is_active: item.is_active,
            starts_at: item.starts_at ? item.starts_at.slice(0, 16) : '',
            ends_at: item.ends_at ? item.ends_at.slice(0, 16) : '',
        });
        setShowForm(true);
    };

    const submit = () => {
        if (editing) {
            form.put(route('admin.announcements.update', editing.id), {
                preserveScroll: true,
                onSuccess: resetForm,
            });
        } else {
            form.post(route('admin.announcements.store'), {
                preserveScroll: true,
                onSuccess: resetForm,
            });
        }
    };

    const toggleActive = (item) => {
        router.patch(route('admin.announcements.toggle', item.id), {}, { preserveScroll: true });
    };

    const deleteItem = (item) => {
        if (!confirm(`¿Eliminar "${item.title}"?`)) return;
        router.delete(route('admin.announcements.destroy', item.id), { preserveScroll: true });
    };

    const items = announcements.data || [];

    return (
        <AuthenticatedLayout>
            <Head title="Anuncios de Pantalla" />

            <div style={{ maxWidth: 1000, margin: '0 auto', padding: '32px 24px', fontFamily: "'Outfit', sans-serif" }}>
                {/* Encabezado */}
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 28 }}>
                    <div>
                        <h1 style={{ fontSize: 28, fontWeight: 800, color: V('--t-text'), margin: 0, letterSpacing: '-0.03em' }}>
                            Anuncios de Pantalla
                        </h1>
                        <p style={{ fontSize: 14, color: V('--t-text-muted'), marginTop: 4 }}>
                            Gestiona los anuncios, noticias y promociones que se muestran en la pantalla pública
                        </p>
                    </div>
                    <button onClick={() => { resetForm(); setShowForm(true); }} style={{
                        padding: '11px 24px', borderRadius: 10, border: 'none', cursor: 'pointer',
                        background: V('--t-blue'), color: '#fff', fontSize: 13, fontWeight: 700,
                        fontFamily: "'Outfit', sans-serif",
                        boxShadow: `0 2px 12px color-mix(in srgb, ${V('--t-blue')} 30%, transparent)`,
                        transition: 'transform 0.2s',
                    }}
                    onMouseEnter={e => e.currentTarget.style.transform = 'translateY(-1px)'}
                    onMouseLeave={e => e.currentTarget.style.transform = 'none'}>
                        + Nuevo anuncio
                    </button>
                </div>

                {/* Flash */}
                {flash?.success && (
                    <div style={{
                        display: 'flex', alignItems: 'center', gap: 8, padding: '12px 16px', marginBottom: 20,
                        background: `color-mix(in srgb, ${V('--t-green')} 8%, transparent)`,
                        border: `1px solid color-mix(in srgb, ${V('--t-green')} 25%, transparent)`,
                        borderRadius: 10, color: V('--t-green'), fontSize: 13, fontWeight: 600,
                    }}>
                        ✓ {flash.success}
                    </div>
                )}

                {/* Formulario */}
                {showForm && (
                    <div style={{
                        background: V('--t-card'), border: `1px solid ${V('--t-border')}`, borderRadius: 16,
                        padding: '24px 28px', marginBottom: 24,
                        animation: 'fadeSlideIn 0.25s ease',
                    }}>
                        <h2 style={{ fontSize: 18, fontWeight: 700, color: V('--t-text'), margin: '0 0 20px' }}>
                            {editing ? 'Editar anuncio' : 'Nuevo anuncio'}
                        </h2>

                        {/* Tipo */}
                        <div style={{ marginBottom: 20 }}>
                            <label style={{
                                display: 'block', fontSize: 10, fontWeight: 700, color: V('--t-text-muted'),
                                textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 8,
                                fontFamily: "'JetBrains Mono', monospace",
                            }}>Tipo</label>
                            <div style={{ display: 'flex', gap: 8 }}>
                                {TYPES.map(t => (
                                    <button key={t.value} type="button" onClick={() => form.setData('type', t.value)}
                                        style={{
                                            flex: 1, padding: '12px 14px', borderRadius: 10, cursor: 'pointer',
                                            border: `1.5px solid ${form.data.type === t.value ? V('--t-blue') : V('--t-border')}`,
                                            background: form.data.type === t.value ? `color-mix(in srgb, ${V('--t-blue')} 6%, transparent)` : V('--t-surface'),
                                            transition: 'all 0.2s', textAlign: 'left',
                                        }}>
                                        <div style={{ fontSize: 13, fontWeight: 600, color: V('--t-text') }}>{t.label}</div>
                                        <div style={{ fontSize: 11, color: V('--t-text-muted'), marginTop: 2 }}>{t.desc}</div>
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* Título */}
                        <div style={{ marginBottom: 20 }}>
                            <label style={{
                                display: 'block', fontSize: 10, fontWeight: 700, color: V('--t-text-muted'),
                                textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 8,
                                fontFamily: "'JetBrains Mono', monospace",
                            }}>Título</label>
                            <input type="text" value={form.data.title} onChange={e => form.setData('title', e.target.value)}
                                placeholder="Ej: Horario especial este viernes"
                                maxLength={255}
                                style={{
                                    width: '100%', padding: '10px 14px',
                                    background: V('--t-surface'), border: `1px solid ${V('--t-border')}`, borderRadius: 10,
                                    color: V('--t-text'), fontSize: 14, outline: 'none',
                                    fontFamily: "'Outfit', sans-serif",
                                }} />
                            {form.errors.title && <div style={{ fontSize: 11, color: V('--t-red'), marginTop: 4 }}>{form.errors.title}</div>}
                        </div>

                        {/* Cuerpo */}
                        <div style={{ marginBottom: 20 }}>
                            <label style={{
                                display: 'block', fontSize: 10, fontWeight: 700, color: V('--t-text-muted'),
                                textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 8,
                                fontFamily: "'JetBrains Mono', monospace",
                            }}>Descripción (opcional)</label>
                            <textarea value={form.data.body} onChange={e => form.setData('body', e.target.value)}
                                placeholder="Texto adicional que se mostrará debajo del título"
                                maxLength={1000} rows={3}
                                style={{
                                    width: '100%', padding: '10px 14px',
                                    background: V('--t-surface'), border: `1px solid ${V('--t-border')}`, borderRadius: 10,
                                    color: V('--t-text'), fontSize: 14, outline: 'none', resize: 'vertical',
                                    fontFamily: "'Outfit', sans-serif",
                                }} />
                        </div>

                        {/* Fila: Sucursal + Prioridad */}
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 20 }}>
                            <div>
                                <label style={{
                                    display: 'block', fontSize: 10, fontWeight: 700, color: V('--t-text-muted'),
                                    textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 8,
                                    fontFamily: "'JetBrains Mono', monospace",
                                }}>Sucursal</label>
                                <select value={form.data.branch_id} onChange={e => form.setData('branch_id', e.target.value)}
                                    style={{
                                        width: '100%', padding: '10px 14px',
                                        background: V('--t-surface'), border: `1px solid ${V('--t-border')}`, borderRadius: 10,
                                        color: V('--t-text'), fontSize: 14, outline: 'none', cursor: 'pointer',
                                        fontFamily: "'Outfit', sans-serif", appearance: 'none',
                                    }}>
                                    <option value="">Todas las sucursales</option>
                                    {branches.map(b => (
                                        <option key={b.id} value={b.id}>{b.name} ({b.code})</option>
                                    ))}
                                </select>
                                <div style={{ fontSize: 11, color: V('--t-text-muted'), marginTop: 4 }}>
                                    Dejar vacío para mostrar en todas
                                </div>
                            </div>
                            <div>
                                <label style={{
                                    display: 'block', fontSize: 10, fontWeight: 700, color: V('--t-text-muted'),
                                    textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 8,
                                    fontFamily: "'JetBrains Mono', monospace",
                                }}>Prioridad</label>
                                <input type="number" value={form.data.priority} min={0} max={100}
                                    onChange={e => form.setData('priority', parseInt(e.target.value) || 0)}
                                    style={{
                                        width: 100, padding: '10px 14px',
                                        background: V('--t-surface'), border: `1px solid ${V('--t-border')}`, borderRadius: 10,
                                        color: V('--t-text'), fontSize: 14, fontFamily: "'JetBrains Mono', monospace",
                                        fontWeight: 700, outline: 'none',
                                    }} />
                                <div style={{ fontSize: 11, color: V('--t-text-muted'), marginTop: 4 }}>
                                    Mayor número = aparece primero
                                </div>
                            </div>
                        </div>

                        {/* Fila: Fechas */}
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 20 }}>
                            <div>
                                <label style={{
                                    display: 'block', fontSize: 10, fontWeight: 700, color: V('--t-text-muted'),
                                    textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 8,
                                    fontFamily: "'JetBrains Mono', monospace",
                                }}>Inicia (opcional)</label>
                                <input type="datetime-local" value={form.data.starts_at}
                                    onChange={e => form.setData('starts_at', e.target.value)}
                                    style={{
                                        width: '100%', padding: '10px 14px',
                                        background: V('--t-surface'), border: `1px solid ${V('--t-border')}`, borderRadius: 10,
                                        color: V('--t-text'), fontSize: 13, outline: 'none',
                                        fontFamily: "'JetBrains Mono', monospace",
                                    }} />
                            </div>
                            <div>
                                <label style={{
                                    display: 'block', fontSize: 10, fontWeight: 700, color: V('--t-text-muted'),
                                    textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 8,
                                    fontFamily: "'JetBrains Mono', monospace",
                                }}>Termina (opcional)</label>
                                <input type="datetime-local" value={form.data.ends_at}
                                    onChange={e => form.setData('ends_at', e.target.value)}
                                    style={{
                                        width: '100%', padding: '10px 14px',
                                        background: V('--t-surface'), border: `1px solid ${V('--t-border')}`, borderRadius: 10,
                                        color: V('--t-text'), fontSize: 13, outline: 'none',
                                        fontFamily: "'JetBrains Mono', monospace",
                                    }} />
                            </div>
                        </div>

                        {/* Botones */}
                        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10, marginTop: 24, paddingTop: 20,
                            borderTop: `1px solid ${V('--t-border')}` }}>
                            <button type="button" onClick={resetForm} style={{
                                padding: '10px 20px', borderRadius: 10, cursor: 'pointer',
                                background: V('--t-surface'), color: V('--t-text-muted'),
                                border: `1px solid ${V('--t-border')}`, fontSize: 13, fontWeight: 600,
                                fontFamily: "'Outfit', sans-serif",
                            }}>Cancelar</button>
                            <button type="button" onClick={submit} disabled={form.processing || !form.data.title.trim()} style={{
                                padding: '10px 24px', borderRadius: 10, border: 'none', cursor: 'pointer',
                                background: V('--t-blue'), color: '#fff', fontSize: 13, fontWeight: 700,
                                fontFamily: "'Outfit', sans-serif",
                                opacity: form.processing || !form.data.title.trim() ? 0.5 : 1,
                                boxShadow: `0 2px 12px color-mix(in srgb, ${V('--t-blue')} 30%, transparent)`,
                            }}>
                                {form.processing ? '⏳ Guardando...' : editing ? 'Actualizar' : 'Crear anuncio'}
                            </button>
                        </div>
                    </div>
                )}

                {/* Lista de anuncios */}
                {items.length === 0 && !showForm ? (
                    <div style={{
                        textAlign: 'center', padding: '60px 20px',
                        background: V('--t-card'), border: `1px solid ${V('--t-border')}`, borderRadius: 16,
                    }}>
                        <div style={{ fontSize: 48, marginBottom: 16 }}>📢</div>
                        <div style={{ fontSize: 18, fontWeight: 700, color: V('--t-text'), marginBottom: 8 }}>
                            Sin anuncios todavía
                        </div>
                        <div style={{ fontSize: 14, color: V('--t-text-muted'), marginBottom: 24 }}>
                            Los anuncios se muestran en la pantalla pública de las sucursales
                        </div>
                        <button onClick={() => setShowForm(true)} style={{
                            padding: '11px 28px', borderRadius: 10, border: 'none', cursor: 'pointer',
                            background: V('--t-blue'), color: '#fff', fontSize: 13, fontWeight: 700,
                            fontFamily: "'Outfit', sans-serif",
                        }}>Crear primer anuncio</button>
                    </div>
                ) : (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                        {items.map(item => (
                            <div key={item.id} style={{
                                background: V('--t-card'), border: `1px solid ${V('--t-border')}`, borderRadius: 14,
                                padding: '18px 22px',
                                display: 'flex', alignItems: 'center', gap: 16,
                                opacity: item.is_active ? 1 : 0.5,
                                transition: 'opacity 0.2s',
                            }}>
                                {/* Tipo badge */}
                                <div style={{
                                    fontSize: 10, fontWeight: 700, padding: '5px 10px', borderRadius: 6,
                                    background: TYPE_COLORS[item.type]?.light || V('--t-surface'),
                                    color: TYPE_COLORS[item.type]?.bg || V('--t-text-muted'),
                                    textTransform: 'uppercase', letterSpacing: '0.06em',
                                    fontFamily: "'JetBrains Mono', monospace", whiteSpace: 'nowrap',
                                }}>
                                    {TYPE_LABELS[item.type] || item.type}
                                </div>

                                {/* Contenido */}
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div style={{ fontSize: 14, fontWeight: 700, color: V('--t-text'), marginBottom: 2 }}>
                                        {item.title}
                                    </div>
                                    <div style={{ display: 'flex', gap: 12, fontSize: 11, color: V('--t-text-muted') }}>
                                        {item.branch ? (
                                            <span>📍 {item.branch.name}</span>
                                        ) : (
                                            <span>🌐 Todas las sucursales</span>
                                        )}
                                        {item.starts_at && <span>Desde: {new Date(item.starts_at).toLocaleDateString('es-MX')}</span>}
                                        {item.ends_at && <span>Hasta: {new Date(item.ends_at).toLocaleDateString('es-MX')}</span>}
                                    </div>
                                </div>

                                {/* Acciones */}
                                <div style={{ display: 'flex', gap: 6, flexShrink: 0 }}>
                                    <button onClick={() => toggleActive(item)} title={item.is_active ? 'Desactivar' : 'Activar'}
                                        style={{
                                            width: 34, height: 34, borderRadius: 8, border: 'none', cursor: 'pointer',
                                            background: item.is_active
                                                ? `color-mix(in srgb, ${V('--t-green')} 10%, transparent)`
                                                : V('--t-surface'),
                                            color: item.is_active ? V('--t-green') : V('--t-text-muted'),
                                            fontSize: 14, display: 'flex', alignItems: 'center', justifyContent: 'center',
                                        }}>
                                        {item.is_active ? '✓' : '○'}
                                    </button>
                                    <button onClick={() => startEdit(item)} title="Editar"
                                        style={{
                                            width: 34, height: 34, borderRadius: 8, border: 'none', cursor: 'pointer',
                                            background: V('--t-surface'), color: V('--t-text-muted'), fontSize: 14,
                                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                                        }}>✎</button>
                                    <button onClick={() => deleteItem(item)} title="Eliminar"
                                        style={{
                                            width: 34, height: 34, borderRadius: 8, border: 'none', cursor: 'pointer',
                                            background: `color-mix(in srgb, ${V('--t-red')} 8%, transparent)`,
                                            color: V('--t-red'), fontSize: 14,
                                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                                        }}>✕</button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <style>{`
                @keyframes fadeSlideIn {
                    from { opacity: 0; transform: translateY(6px); }
                    to { opacity: 1; transform: translateY(0); }
                }
            `}</style>
        </AuthenticatedLayout>
    );
}
