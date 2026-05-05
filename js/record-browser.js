// js/record-browser.js — Record Browser with calendar search
const { useState, useEffect, useMemo } = React;

// --- 16. 记录浏览器 (Record Browser) ---
window.RecordBrowser = ({ logs = {}, notes = {}, relapseRecords = [], energyLogs = {}, onDelete, isLoggedIn }) => {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedDate, setSelectedDate] = useState(null);
    const [viewYear, setViewYear] = useState(new Date().getFullYear());
    const __ = window.__ || (k => k);

    const typeIcons = {
        relapse: { icon: '⚡', label: 'relapse_btn', color: 'text-red-600', bg: 'bg-red-50' },
        sex: { icon: '🛏️', label: 'sex_btn', color: 'text-slate-600', bg: 'bg-slate-50' },
        porn: { icon: '👁️', label: 'porn_btn', color: 'text-yellow-600', bg: 'bg-yellow-50' },
        emission: { icon: '💧', label: 'emission_btn', color: 'text-indigo-600', bg: 'bg-indigo-50' },
        urge: { icon: '🔥', label: 'urge_btn', color: 'text-orange-600', bg: 'bg-orange-50' },
        energy: { icon: '⚡', label: 'type_energy', color: 'text-teal-600', bg: 'bg-teal-50' },
        note: { icon: '📝', label: 'note_title', color: 'text-gray-600', bg: 'bg-gray-50' }
    };

    // Build combined records list
    const allRecords = useMemo(() => {
        const records = [];

        // Log records
        Object.entries(logs).forEach(([date, types]) => {
            if (!Array.isArray(types)) return;
            types.forEach(type => {
                records.push({ date, kind: 'log', type, text: __(typeIcons[type]?.label || type) });
            });
        });

        // Energy records
        Object.entries(energyLogs).forEach(([date, level]) => {
            records.push({ date, kind: 'energy', type: 'energy', text: __('type_energy') + ': ' + level + '/10' });
        });

        // Note records
        Object.entries(notes).forEach(([date, text]) => {
            if (String(text || '').trim() === '') return;
            records.push({ date, kind: 'note', type: 'note', text: String(text) });
        });

        // Relapse reason records
        relapseRecords.forEach(r => {
            records.push({ date: r.date, kind: 'relapse_reason', type: 'relapse', text: r.reason || '' });
        });

        records.sort((a, b) => b.date.localeCompare(a.date));
        return records;
    }, [logs, notes, relapseRecords, energyLogs]);

    // Filter records
    const filteredRecords = useMemo(() => {
        let result = allRecords;
        if (selectedDate) {
            result = result.filter(r => r.date === selectedDate);
        }
        if (searchQuery.trim()) {
            const q = searchQuery.trim().toLowerCase();
            result = result.filter(r =>
                r.date.includes(q) ||
                r.text.toLowerCase().includes(q) ||
                (r.type && __(typeIcons[r.type]?.label || r.type).toLowerCase().includes(q))
            );
        }
        return result;
    }, [allRecords, searchQuery, selectedDate]);

    // Build calendar data for selected year
    const yearMonths = useMemo(() => {
        const months = [];
        for (let m = 0; m < 12; m++) {
            const daysInMonth = new Date(viewYear, m + 1, 0).getDate();
            const firstDay = new Date(viewYear, m, 1).getDay();
            const days = [];
            for (let d = 1; d <= daysInMonth; d++) {
                const dateStr = `${viewYear}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                const dayLogs = logs[dateStr] || [];
                const hasNote = notes[dateStr] && String(notes[dateStr]).trim() !== '';
                const hasEnergy = energyLogs[dateStr] !== undefined;
                let status = 0;
                if (dayLogs.includes('relapse') || dayLogs.includes('sex')) status = 3;
                else if (dayLogs.includes('porn')) status = 2;
                else if (dayLogs.includes('urge') || dayLogs.includes('emission')) status = 1;
                days.push({ day: d, dateStr, status, hasNote, hasEnergy, firstDay: d === 1 ? firstDay : null });
            }
            months.push({ month: m, days, label: `${viewYear}-${String(m + 1).padStart(2, '0')}` });
        }
        return months;
    }, [viewYear, logs, notes, energyLogs]);

    const totalLogs = Object.values(logs).reduce((sum, arr) => sum + (Array.isArray(arr) ? arr.length : 0), 0);
    const totalNotes = Object.values(notes).filter(t => String(t || '').trim() !== '').length;

    return (
        <div className="mt-6">
            <button
                onClick={() => setIsModalOpen(true)}
                className="w-full flex items-center justify-between px-5 py-4 bg-white rounded-2xl border border-gray-100 shadow-md text-gray-700 hover:bg-gray-50 transition-all active:scale-[0.98]"
            >
                <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full bg-indigo-50 flex items-center justify-center text-xl shadow-sm border border-indigo-100">{'📋'}</div>
                    <div className="text-left">
                        <span className="font-bold text-base block">{__('record_browser_title')}</span>
                        <div className="flex items-center gap-2 mt-0.5">
                            <span className="text-[10px] text-gray-400">{totalLogs} {__('record_total_logs')}</span>
                            <span className="text-[9px] bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded-full font-bold">{totalNotes} {__('record_total_notes')}</span>
                        </div>
                    </div>
                </div>
                <div className="text-gray-400 bg-gray-100 rounded-full p-2">
                    <window.ChevronRight size={18} />
                </div>
            </button>

            {isModalOpen && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-0 sm:p-4 lg:p-8">
                    <div className="absolute inset-0 bg-gray-900/60 backdrop-blur-md transition-opacity" onClick={() => setIsModalOpen(false)}></div>
                    <div className="relative bg-[#f8fafc] w-full h-full sm:h-auto sm:rounded-3xl max-w-5xl max-h-full sm:max-h-[90vh] overflow-hidden shadow-2xl animate-fade-in flex flex-col border border-white/50">

                        {/* Header */}
                        <div className="flex items-center justify-between px-6 py-5 bg-white border-b border-gray-100 shrink-0">
                            <div className="flex items-center gap-3">
                                <div className="p-2 bg-indigo-50 text-indigo-600 rounded-xl">{'📋'}</div>
                                <div>
                                    <h3 className="text-lg font-bold text-gray-800">{__('record_browser_title')}</h3>
                                    <p className="text-[10px] text-gray-400 uppercase tracking-widest">{__('record_browser_subtitle')}</p>
                                </div>
                            </div>
                            <button onClick={() => setIsModalOpen(false)} className="p-2 bg-gray-100 hover:bg-gray-200 rounded-full text-gray-500 transition-colors">
                                <window.CloseIcon size={18} />
                            </button>
                        </div>

                        {/* Body */}
                        <div className="overflow-y-auto p-4 sm:p-6 space-y-5 custom-scrollbar bg-slate-50/50 flex-1">

                            {/* Search bar + Year nav */}
                            <div className="flex items-center gap-3 flex-wrap">
                                <div className="flex-1 min-w-[200px] relative">
                                    <input
                                        type="text"
                                        value={searchQuery}
                                        onChange={e => setSearchQuery(e.target.value)}
                                        placeholder={__('record_browser_search')}
                                        className="w-full px-4 py-2.5 pl-10 bg-white border border-gray-200 rounded-xl text-sm text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-transparent transition"
                                    />
                                    <svg className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                </div>
                                {selectedDate && (
                                    <button
                                        onClick={() => setSelectedDate(null)}
                                        className="px-3 py-2 bg-indigo-100 text-indigo-700 rounded-lg text-xs font-bold hover:bg-indigo-200 transition flex items-center gap-1"
                                    >
                                        {selectedDate} <span className="text-indigo-400">{'✕'}</span>
                                    </button>
                                )}
                                <div className="flex items-center gap-1">
                                    <button onClick={() => setViewYear(y => y - 1)} className="p-1.5 hover:bg-white rounded-lg transition">
                                        <window.ChevronLeft size={16} className="text-gray-500" />
                                    </button>
                                    <span className="text-sm font-bold text-gray-700 min-w-[60px] text-center">{viewYear}</span>
                                    <button onClick={() => setViewYear(y => y + 1)} className="p-1.5 hover:bg-white rounded-lg transition">
                                        <window.ChevronRight size={16} className="text-gray-500" />
                                    </button>
                                </div>
                            </div>

                            {/* Year Calendar Grid - 12 mini months */}
                            <div className="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
                                <h4 className="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                                    <span className="w-1.5 h-4 bg-indigo-400 rounded-full"></span> {viewYear}
                                </h4>
                                <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3">
                                    {yearMonths.map(m => (
                                        <div key={m.month} className="text-center">
                                            <div className="text-[10px] font-bold text-gray-500 mb-1">{m.label}</div>
                                            <div className="grid grid-cols-7 gap-[1px]">
                                                {['S','M','T','W','T','F','S'].map((d, i) => (
                                                    <div key={i} className="text-[7px] text-gray-300 font-bold">{d}</div>
                                                ))}
                                                {m.days[0].firstDay > 0 && Array(m.days[0].firstDay).fill(null).map((_, i) => (
                                                    <div key={'pad-' + i} className="w-full aspect-square"></div>
                                                ))}
                                                {m.days.map(d => {
                                                    let bg = 'bg-slate-100';
                                                    if (d.status === 1) bg = 'bg-orange-200';
                                                    if (d.status === 2) bg = 'bg-orange-400';
                                                    if (d.status === 3) bg = 'bg-red-500';
                                                    if (d.status === 0 && d.hasNote) bg = 'bg-indigo-200';
                                                    if (d.status === 0 && !d.hasNote && !d.hasEnergy) bg = 'bg-gray-100';

                                                    const isSelected = selectedDate === d.dateStr;
                                                    return (
                                                        <div
                                                            key={d.dateStr}
                                                            onClick={() => setSelectedDate(isSelected ? null : d.dateStr)}
                                                            title={d.dateStr}
                                                            className={`w-full aspect-square rounded-[1px] cursor-pointer transition-all hover:scale-125 hover:z-10 ${bg} ${isSelected ? 'ring-2 ring-indigo-500 ring-offset-1 scale-125 z-10' : ''}`}
                                                        ></div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                <div className="mt-4 flex items-center gap-4 text-[10px] text-gray-400 flex-wrap">
                                    <div className="flex items-center gap-1"><div className="w-2 h-2 bg-gray-100 rounded-sm"></div> {__('record_browser_empty')}</div>
                                    <div className="flex items-center gap-1"><div className="w-2 h-2 bg-orange-200 rounded-sm"></div> {__('urge_btn')}</div>
                                    <div className="flex items-center gap-1"><div className="w-2 h-2 bg-orange-400 rounded-sm"></div> {__('porn_btn')}</div>
                                    <div className="flex items-center gap-1"><div className="w-2 h-2 bg-red-500 rounded-sm"></div> {__('relapse_btn')}/{__('sex_btn')}</div>
                                    <div className="flex items-center gap-1"><div className="w-2 h-2 bg-indigo-200 rounded-sm"></div> {__('note_title')}</div>
                                </div>
                            </div>

                            {/* Record Listing */}
                            <div className="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
                                <h4 className="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                                    <span className="w-1.5 h-4 bg-indigo-400 rounded-full"></span>
                                    {selectedDate
                                        ? selectedDate + ' — ' + filteredRecords.length + ' ' + __('record_total_logs')
                                        : __('record_browser_all') + ' (' + filteredRecords.length + ')'
                                    }
                                </h4>
                                {filteredRecords.length === 0 ? (
                                    <div className="text-center py-10 text-gray-400">
                                        <div className="text-3xl mb-2">{'🔍'}</div>
                                        <p className="text-xs">{__('record_browser_empty')}</p>
                                    </div>
                                ) : (
                                    <div className="space-y-1.5 max-h-[400px] overflow-y-auto custom-scrollbar pr-1">
                                        {filteredRecords.map((r, i) => {
                                            const meta = typeIcons[r.type] || typeIcons.note;
                                            return (
                                                <div key={i} className={`flex items-start gap-3 px-3 py-2.5 rounded-xl ${meta.bg} hover:shadow-sm transition group`}>
                                                    <span className="text-base mt-0.5 flex-shrink-0">{meta.icon}</span>
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-center gap-2 flex-wrap">
                                                            <span className="text-[10px] font-mono text-gray-500 font-bold">{r.date}</span>
                                                            <span className={`text-[10px] font-bold ${meta.color}`}>{__(meta.label)}</span>
                                                            {r.kind === 'energy' && (
                                                                <span className="text-[10px] text-teal-600 font-bold">{r.text.split(': ')[1]}</span>
                                                            )}
                                                        </div>
                                                        {(r.kind === 'note' || r.kind === 'relapse_reason') && r.text && (
                                                            <div className="text-xs text-gray-700 mt-1 whitespace-pre-wrap break-words">{r.text}</div>
                                                        )}
                                                    </div>
                                                    {isLoggedIn && onDelete && (r.kind === 'log') && (
                                                        <button
                                                            onClick={() => onDelete(r.date, r.type)}
                                                            className="p-1 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 opacity-0 group-hover:opacity-100 transition-all flex-shrink-0"
                                                            title={__('confirm_delete_title')}
                                                        >
                                                            <window.TrashIcon size={12} />
                                                        </button>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>

                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};
