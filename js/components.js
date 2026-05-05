// js/components.js
const { useState, useEffect, useMemo } = React;

// --- 辅助函数：获取本地 YYYY-MM-DD ---
const getLocalYMD = (dateObj) => {
    const year = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const day = String(dateObj.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// --- 1. 基础图标系统 ---
const Icon = ({ size = 24, children, className = "", style = {} }) => (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className} style={style}>
        {children}
    </svg>
);

// 导出图标到 window
window.CalendarIcon = (p) => <Icon {...p}><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></Icon>;
window.Activity = (p) => <Icon {...p}><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></Icon>;
window.EyeOff = (p) => <Icon {...p}><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7c.44 0 .87-.03 1.28-.08"/><line x1="2" x2="22" y1="2" y2="22"/></Icon>;
window.ShieldAlert = (p) => <Icon {...p}><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></Icon>;
window.Zap = (p) => <Icon {...p}><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></Icon>;
window.Droplets = (p) => <Icon {...p}><path d="M7 16.3c2.2 0 4-1.83 4-4.05 0-1.16-.57-2.26-1.71-3.19S7.29 6.75 7 5.3c-.29 1.45-1.14 2.84-2.29 3.76S3 11.1 3 12.25c0 2.22 1.8 4.05 4 4.05z"/><path d="M12.56 6.6A10.97 10.97 0 0 0 14 3.02c.5 2.5 2 4.9 4 6.5s3 3.5 3 5.5a6.98 6.98 0 0 1-11.91 4.97"/></Icon>;
window.LogOut = (p) => <Icon {...p}><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></Icon>;
window.ChevronLeft = (p) => <Icon {...p}><polyline points="15 18 9 12 15 6" /></Icon>;
window.ChevronRight = (p) => <Icon {...p}><polyline points="9 18 15 12 9 6" /></Icon>;
window.Trophy = (p) => <Icon {...p}><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></Icon>;
window.CloseIcon = (p) => <Icon {...p}><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></Icon>;
window.TrashIcon = (p) => <Icon {...p}><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></Icon>;
window.CheckIcon = (p) => <Icon {...p}><polyline points="20 6 9 17 4 12"/></Icon>;

// --- 2. 核心植物组件 (PlantStage) ---
window.PlantStage = ({ score }) => {
    if (score <= 60) {
        // 幼苗/枯萎状态
        return (
            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#d97706" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                <path d="M12 22v-7" />
                <path d="M12 15a5 5 0 0 0-5-5c0-2 2-3 5-3s5 1 5 3a5 5 0 0 0-5 5" opacity="0.5"/>
                <path d="M8 8c-1.5-1-2-3-1-4" />
                <path d="M16 8c1.5-1 2-3 1-4" />
                <path d="M12 22c-2 0-3-1-3-2" />
            </svg>
        );
    }
    if (score <= 90) {
        // 成长状态
        return (
            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#4ade80" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M12 22v-10" />
                <path d="M12 12c0-4 3-6 6-6s3 2 3 6" />
                <path d="M12 12c0-4-3-6-6-6s-3 2-3 6" />
            </svg>
        );
    }
    // 繁盛状态
    return (
        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#2dd4bf" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 22v-6" />
            <path d="M12 8c0-4 3-5 5-5s3 2 3 6-3 6-8 6" />
            <path d="M12 8c0-4-3-5-5-5s-3 2-3 6 3 6 8 6" />
            <path d="M12 16c0-2 2-4 4-4" />
            <path d="M12 16c0-2-2-4-4-4" />
        </svg>
    );
};

// --- 3. 双重统计面板 (DualStats) ---
window.DualStats = ({ relapseStreak, pornStreak, score }) => {
    const __ = window.__ || (k => k);
    return (
        <div className="w-full">
            <div className="grid grid-cols-2 gap-4 w-full px-8 mt-4 mb-4">
                <div className="flex flex-col items-center">
                    <span className="text-[10px] uppercase tracking-widest opacity-70 text-white">{__('relapse_label')}</span>
                    <span className="text-4xl font-bold tabular-nums tracking-tighter leading-none text-white">{relapseStreak}</span>
                    <span className="text-[10px] opacity-80 text-white">{__('days_unit')}</span>
                </div>
                <div className="flex flex-col items-center border-l border-white/20">
                    <span className="text-[10px] uppercase tracking-widest opacity-70 text-white">{__('porn_label')}</span>
                    <span className="text-4xl font-bold tabular-nums tracking-tighter leading-none text-white">{pornStreak}</span>
                    <span className="text-[10px] opacity-80 text-white">{__('days_unit')}</span>
                </div>
            </div>

            <div className="flex justify-center">
                <div className="bg-black/20 backdrop-blur-md px-3 py-1 rounded-full border border-white/10 flex items-center gap-2">
                    <div className={`w-2 h-2 rounded-full ${score > 80 ? 'bg-green-400' : score > 50 ? 'bg-yellow-400' : 'bg-red-400'}`}></div>
                    <span className="text-xs font-bold text-white">{__('score_label')} {score.toFixed(1)}</span>
                </div>
            </div>
        </div>
    );
};

// --- 13. 每日隨筆 ---
window.DailyNote = ({ note, onSave }) => {
    const [text, setText] = useState(note || '');
    const [saved, setSaved] = useState(false);
    const __ = window.__ || (k => k);

    useEffect(() => {
        setText(note || '');
        setSaved(!!note);
    }, [note]);

    const handleSave = () => {
        onSave(text);
        setSaved(true);
        setTimeout(() => setSaved(false), 2000);
    };

    return (
        <div className="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm w-full max-w-[320px] mt-4 group">
            <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2">
                    <span className="text-lg">📝</span>
                    <span className="font-bold text-gray-700 text-sm">{__('note_title')}</span>
                </div>
                {saved && (
                    <span className="text-[10px] text-teal-500 font-bold animate-fade-in">{__('note_saved')}</span>
                )}
            </div>
            <textarea
                value={text}
                onChange={(e) => setText(e.target.value)}
                placeholder={__('note_placeholder')}
                rows={3}
                className="w-full bg-slate-50 border border-slate-100 rounded-xl p-3 text-sm text-gray-700 placeholder-gray-400 resize-none focus:outline-none focus:ring-2 focus:ring-teal-300 focus:border-transparent transition"
            />
            <button
                onClick={handleSave}
                className="w-full mt-2 py-2 bg-teal-50 text-teal-600 rounded-xl font-bold text-xs hover:bg-teal-100 active:scale-[0.98] transition"
            >
                {__('note_save')}
            </button>
        </div>
    );
};

// --- 13.1 每日隨筆歷史 ---
window.NoteHistory = ({ notes = {}, onDelete }) => {
    const __ = window.__ || (k => k);
    const items = Object.entries(notes || {})
        .filter(([, text]) => String(text || '').trim() !== '')
        .sort((a, b) => b[0].localeCompare(a[0]))
        .slice(0, 14);

    return (
        <div className="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm w-full max-w-[320px] mt-3">
            <div className="flex items-center gap-2 mb-3">
                <span className="text-base">📚</span>
                <span className="font-bold text-gray-700 text-sm">{__('note_history_title')}</span>
            </div>
            {items.length === 0 ? (
                <div className="text-xs text-gray-400 py-2">{__('note_history_empty')}</div>
            ) : (
                <div className="space-y-2 max-h-48 overflow-y-auto custom-scrollbar pr-1">
                    {items.map(([date, text]) => (
                        <div key={date} className="rounded-xl border border-slate-100 bg-slate-50 p-2.5 group relative">
                            <div className="text-[10px] font-mono text-teal-600 mb-1">{date}</div>
                            <div className="text-xs text-gray-700 whitespace-pre-wrap break-words pr-6">{text}</div>
                            {onDelete && (
                                <button
                                    onClick={() => onDelete(date)}
                                    className="absolute top-2 right-2 w-5 h-5 flex items-center justify-center rounded-full text-gray-400 hover:text-red-500 hover:bg-red-50 opacity-0 group-hover:opacity-100 transition-all"
                                    title={__('note_delete_btn')}
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
};

// --- 14. 里程碑慶祝彈窗 ---
window.AchievementModal = ({ isOpen, days, onClose }) => {
    const __ = window.__ || (k => k);
    if (!isOpen) return null;

    const milestones = [7, 14, 21, 28, 50, 100, 150, 200, 300, 365];
    if (!milestones.includes(days)) return null;

    return (
        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 animate-fade-in">
            <div className="absolute inset-0 bg-gray-900/80 backdrop-blur-md" onClick={onClose}></div>
            <div className="relative bg-white rounded-3xl p-8 max-w-sm w-full text-center shadow-2xl animate-bounce-small border border-white/50">
                <div className="text-6xl mb-4">🏆</div>
                <h2 className="text-xl font-black text-gray-800 mb-2">{__('celebration_title')}</h2>
                <p className="text-gray-600 text-sm leading-relaxed mb-6">
                    {__('celebration_msg').replace('{n}', days)}
                </p>
                <div className="flex justify-center gap-2 mb-4">
                    {[7, 14, 21, 28, 50, 100, 150, 200, 300, 365].map(m => (
                        <div key={m} className={`w-2 h-2 rounded-full ${days >= m ? 'bg-teal-500' : 'bg-gray-200'}`}></div>
                    ))}
                </div>
                <button
                    onClick={onClose}
                    className="px-8 py-3 bg-teal-600 text-white font-bold rounded-xl shadow-lg shadow-teal-200 hover:bg-teal-700 active:scale-95 transition"
                >
                    {__('reason_confirm')}
                </button>
            </div>
        </div>
    );
};

// --- 4. 紧凑型日历气泡 (DayPopover) ---
window.DayPopover = ({ dateStr, logs, onClose, onDelete }) => {
    const { Zap, EyeOff, Activity, CloseIcon, TrashIcon, Droplets } = window;
    const isLoggedIn = window.SouleanConfig?.isLoggedIn;
    const __ = window.__ || (k => k);

    return (
        <div className="bg-white p-4 rounded-xl shadow-xl border border-gray-100 w-52 text-left animate-fade-in relative z-50">
             <div className="flex justify-between items-center mb-3 pb-2 border-b border-gray-100">
                <span className="font-bold text-gray-700 text-sm">{dateStr}</span>
                <button onClick={(e) => { e.stopPropagation(); onClose(); }} className="text-gray-400 hover:text-gray-600 transition-colors bg-gray-50 rounded-full p-1"><CloseIcon size={14}/></button>
             </div>
             <div className="space-y-2 max-h-[200px] overflow-y-auto custom-scrollbar">
                {logs.length === 0 ? (
                    <div className="text-xs text-gray-400 text-center py-2">{__('no_log')}</div>
                ) : logs.map((type, i) => {
                    let label, textClass, bgClass, icon;

                    if (type === 'relapse') {
                        label=__('relapse_btn');
                        textClass='text-red-700';
                        bgClass='bg-red-50 border-red-100';
                        icon = <Zap size={14} className="text-red-500" fill="currentColor"/>;
                    } else if (type === 'sex') {
                        label=__('sex_btn');
                        textClass='text-slate-700';
                        bgClass='bg-slate-100 border-slate-200';
                        icon = <span className="text-[13px] leading-none">🛏️</span>;
                    } else if (type === 'emission') {
                        label=__('emission_btn');
                        textClass='text-indigo-700';
                        bgClass='bg-indigo-50 border-indigo-100';
                        icon = <Droplets size={14} className="text-indigo-500"/>;
                    } else if (type === 'porn') {
                        label=__('porn_btn');
                        textClass='text-yellow-700';
                        bgClass='bg-yellow-50 border-yellow-100';
                        icon = <EyeOff size={14} className="text-yellow-600"/>;
                    } else {
                        label=__('urge_btn');
                        textClass='text-orange-700';
                        bgClass='bg-orange-50 border-orange-100';
                        icon = <Activity size={14} className="text-orange-500"/>;
                    }
                    
                    return (
                        <div key={i} className={`flex items-center justify-between text-xs px-3 py-2 rounded-lg border ${bgClass} transition-all`}>
                            <div className="flex items-center gap-2">
                                {icon}
                                <span className={`font-bold ${textClass}`}>{label}</span>
                            </div>
                            {isLoggedIn && (
                                <button 
                                            onClick={(e) => { e.stopPropagation(); onDelete(dateStr, type); }} 
                                            className="text-gray-400 hover:text-red-500 p-1 rounded hover:bg-white transition-all"
                                            title={__('confirm_delete_title')}
                                        >
                                    <TrashIcon size={12}/>
                                </button>
                            )}
                        </div>
                    );
                })}
             </div>
        </div>
    );
};

// --- 5. 日历单日组件 (CalendarDay) ---
window.CalendarDay = ({ dayNum, isToday, isFuture, checkDate, now, logs = [], onClick, isSelected, isDesktop, dateStr, onClosePopover, onDelete }) => {
    const { Zap, EyeOff, DayPopover } = window;
    
    const hasPorn = logs.includes('porn');
    const hasUrge = logs.includes('urge');
    const hasRelapse = logs.includes('relapse');
    const hasSex = logs.includes('sex');
    const hasEmission = logs.includes('emission');

    // 默认样式
    let bgClass = 'bg-transparent hover:bg-gray-50'; 
    let textClass = 'text-gray-600';
    let ringClass = '';

    // 1. 过去日期的基础样式
    if (!isFuture && checkDate < now) { 
        bgClass = 'bg-teal-50/30'; 
        textClass = 'text-teal-900';
    }
    
    // 2. 状态覆盖样式 (优先级：破戒/看片 > 今天 > 普通)
    // 只要有破戒或看片，背景直接变黑，文字变白，起到强烈警示
    if (hasRelapse || hasPorn || hasSex) {
        bgClass = 'bg-gray-900 shadow-sm';
        textClass = 'font-bold text-white';
    }

    // 3. 今天的特殊样式
    if (isToday) {
        // 如果今天没有破戒/看片，显示原本的 Teal 高亮
        if (!hasRelapse && !hasPorn && !hasSex) {
            bgClass = 'bg-teal-600 text-white shadow-md shadow-teal-200';
            textClass = 'font-bold text-white';
        }
        // 今天始终有圈圈
        ringClass = 'ring-2 ring-teal-400 ring-offset-2 z-10';
    }

    // 选中状态
    if (isSelected) {
        ringClass = 'ring-2 ring-teal-400 ring-offset-2 z-10';
    }

    // 未来日期
    if (isFuture) {
        textClass = 'text-gray-300';
        bgClass = 'bg-transparent cursor-default hover:bg-transparent';
    }

    return (
        <div 
            onClick={!isFuture ? onClick : undefined} 
            className={`flex flex-col items-center relative h-10 w-10 justify-center group ${!isFuture ? 'cursor-pointer' : ''}`}
        >
            <div className={`w-8 h-8 flex items-center justify-center rounded-xl text-xs transition-all duration-200 ${bgClass} ${textClass} ${ringClass}`}>
                {dayNum}
            </div>
            
            {/* 状态标记点/图标 - 显示在日期下方 */}
            <div className="absolute -bottom-1.5 flex gap-1 justify-center w-full h-3 items-center">
                {hasRelapse ? (
                    <div className="bg-white rounded-full p-[1px] shadow-sm flex items-center justify-center border border-gray-100 relative z-10">
                        <Zap size={10} className="text-red-500" fill="currentColor" />
                    </div>
                ) : hasSex ? (
                    <div className="bg-white rounded-full px-[2px] py-[1px] shadow-sm flex items-center justify-center border border-gray-100 relative z-10 text-[9px] leading-none">
                        🛏️
                    </div>
                ) : hasPorn ? (
                    <div className="bg-white rounded-full p-[1px] shadow-sm flex items-center justify-center border border-gray-100 relative z-10">
                        <EyeOff size={10} className="text-yellow-600" />
                    </div>
                ) : hasEmission ? (
                    <div className="w-1.5 h-1.5 bg-indigo-400 rounded-full border border-white shadow-sm"></div>
                ) : hasUrge ? (
                    <div className="w-1.5 h-1.5 bg-red-400 rounded-full border border-white shadow-sm"></div>
                ) : null}
            </div>

            {/* Desktop Popover */}
            {isDesktop && isSelected && (
                <div className="absolute bottom-full mb-3 left-1/2 transform -translate-x-1/2 z-50 filter drop-shadow-xl animate-bounce-small">
                    <DayPopover dateStr={dateStr} logs={logs} onClose={onClosePopover} onDelete={onDelete} />
                    <div className="w-4 h-4 bg-white transform rotate-45 absolute -bottom-1.5 left-1/2 -translate-x-1/2 border-r border-b border-gray-100"></div>
                </div>
            )}
        </div>
    );
};

// --- 6. 破戒原因问卷弹窗 ---
window.RelapseReasonModal = ({ isOpen, onClose, onConfirm }) => {
    const [reason, setReason] = useState("");
    const __ = window.__ || (k => k);
    const reasons = [
        { id: "boredom", icon: "🥱", label: __('reason_boredom') },
        { id: "stress", icon: "🤯", label: __('reason_stress') },
        { id: "insomnia", icon: "😴", label: __('reason_insomnia') },
        { id: "trigger", icon: "👙", label: __('reason_trigger') },
        { id: "alone", icon: "🏠", label: __('reason_alone') },
        { id: "habit", icon: "🤖", label: __('reason_habit') }
    ];

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-gray-900/90 backdrop-blur-sm transition-opacity" onClick={onClose}></div>
            <div className="relative bg-white rounded-2xl p-6 w-full max-w-sm shadow-2xl animate-fade-in border border-gray-200">
                <h3 className="text-xl font-bold text-gray-800 mb-2 text-center">{__('reason_title')}</h3>
                <p className="text-gray-500 text-xs text-center mb-6">{__('reason_subtitle')}</p>
                
                <div className="grid grid-cols-2 gap-3 mb-6">
                    {reasons.map(r => (
                        <button 
                            key={r.id}
                            onClick={() => setReason(r.id)}
                            className={`flex flex-col items-center justify-center p-3 rounded-xl border transition-all ${
                                reason === r.id 
                                ? 'border-red-500 bg-red-50 text-red-700 shadow-md transform scale-105' 
                                : 'border-gray-100 bg-gray-50 text-gray-600 hover:bg-gray-100'
                            }`}
                        >
                            <span className="text-2xl mb-1">{r.icon}</span>
                            <span className="text-xs font-bold">{r.label}</span>
                        </button>
                    ))}
                </div>

                <div className="flex gap-3">
                    <button onClick={onClose} className="flex-1 py-3 text-gray-500 font-bold bg-gray-100 rounded-xl hover:bg-gray-200 transition">{__('reason_cancel')}</button>
                    <button 
                        onClick={() => onConfirm(reason || "habit")} 
                        className="flex-1 py-3 bg-red-600 text-white font-bold rounded-xl shadow-lg shadow-red-200 hover:bg-red-700 transition active:scale-95 disabled:opacity-50"
                        disabled={!reason}
                    >
                        {__('reason_confirm')}
                    </button>
                </div>
            </div>
        </div>
    );
};

// --- 7. 精力打分组件 ---
window.EnergyInput = ({ todayEnergy, onSave }) => {
    const [level, setLevel] = useState(todayEnergy || 5);
    const [saved, setSaved] = useState(!!todayEnergy);
    const __ = window.__ || (k => k);

    if (saved) {
        return (
            <div className="bg-teal-50/50 rounded-2xl p-4 border border-teal-100 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full bg-teal-100 text-teal-600 flex items-center justify-center font-bold text-lg">
                        {todayEnergy || level}
                    </div>
                    <div>
                        <div className="text-sm font-bold text-teal-800">{__('today_energy_title')}</div>
                        <div className="text-xs text-teal-600">{__('today_energy_desc')}</div>
                    </div>
                </div>
                <div className="text-teal-400"><window.CheckIcon size={20}/></div>
            </div>
        );
    }

    return (
        <div className="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
            <h4 className="text-sm font-bold text-gray-700 mb-4 flex justify-between">
                <span>{__('energy_title')}</span>
                <span className="text-teal-600 font-mono text-lg">{level}</span>
            </h4>
            <input 
                type="range" min="1" max="10" value={level} 
                onChange={(e) => setLevel(parseInt(e.target.value))}
                className="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-teal-600 mb-4"
            />
            <div className="flex justify-between text-xs text-gray-400 mb-4 px-1">
                <span>{__('energy_low')} (1)</span>
                <span>{__('energy_high')} (10)</span>
            </div>
            <button 
                onClick={() => { onSave(level); setSaved(true); }}
                className="w-full py-2 bg-teal-600 text-white rounded-lg font-bold text-sm shadow-md hover:bg-teal-700 transition"
            >
                {__('energy_save')}
            </button>
        </div>
    );
};



// --- 9. 确认弹窗 ---
window.ConfirmModal = ({ isOpen, title, message, onConfirm, onCancel, type }) => {
    if (!isOpen) return null;
    const __ = window.__ || (k => k);
    let confirmBtnClass = "bg-teal-600 hover:bg-teal-700 shadow-teal-200";
    if (type === 'relapse') confirmBtnClass = "bg-gray-800 hover:bg-black shadow-gray-400";
    if (type === 'emission') confirmBtnClass = "bg-indigo-600 hover:bg-indigo-700 shadow-indigo-200";
    if (type === 'porn') confirmBtnClass = "bg-yellow-500 hover:bg-yellow-600 shadow-yellow-200 text-yellow-900";
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-teal-900/40 backdrop-blur-sm transition-opacity" onClick={onCancel}></div>
            <div className="relative bg-white rounded-2xl p-6 w-full max-w-sm shadow-2xl transform transition-all animate-fade-in scale-100">
                <h3 className="text-xl font-bold text-gray-800 mb-2">{title}</h3>
                <p className="text-gray-600 mb-8 text-sm leading-relaxed whitespace-pre-line opacity-80">{typeof message === 'string' ? message.replace(/\\n/g, '\n') : message}</p>
                <div className="flex gap-3 justify-end">
                    <button onClick={onCancel} className="px-5 py-2.5 text-gray-500 font-medium hover:bg-gray-100 rounded-xl transition-colors text-sm">{__('reason_cancel')}</button>
                    <button onClick={onConfirm} className={`px-6 py-2.5 text-white font-bold rounded-xl shadow-lg transition-all active:scale-95 text-sm ${confirmBtnClass}`}>{__('reason_confirm')}</button>
                </div>
            </div>
        </div>
    );
};

// --- 10. 操作按钮 ---
window.ActionButton = ({ icon, label, color, textColor = "text-white", onClick }) => (
    <button onClick={onClick} className="flex flex-col items-center gap-2 group w-full">
        <div className={`w-14 h-14 rounded-2xl ${color} ${textColor} flex items-center justify-center shadow-lg transition-transform group-hover:scale-110 group-active:scale-95 border-2 border-white/20`}>
            {icon}
        </div>
        <span className="text-xs font-bold text-white/80 group-hover:text-white transition-colors">{label}</span>
    </button>
);

// --- 11. 呼吸急救 ---
window.PanicModal = ({ onClose }) => {
    const [step, setStep] = useState(0); 
    const [timer, setTimer] = useState(0);
    const __ = window.__ || (k => k);
    useEffect(() => {
        let interval;
        if (step > 0) {
            interval = setInterval(() => {
                setTimer((prev) => {
                    if (prev <= 1) {
                        if (step === 1) { setStep(2); return 4; } 
                        if (step === 2) { setStep(3); return 6; } 
                        if (step === 3) { setStep(1); return 4; } 
                        return 4;
                    }
                    return prev - 1;
                });
            }, 1000);
        }
        return () => clearInterval(interval);
    }, [step]);
    const startBreathing = () => { setStep(1); setTimer(4); };
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-teal-900/90 backdrop-blur-md" onClick={onClose}></div>
            <div className="relative bg-white rounded-3xl p-8 max-w-sm w-full text-center shadow-2xl animate-fade-in">
                <h2 className="text-2xl font-bold text-teal-800 mb-2">{__('breathing_title')}</h2>
                <div className="h-1 w-20 bg-teal-100 mx-auto mb-6 rounded-full"></div>
                {step === 0 ? (
                    <div className="space-y-6">
                        <div className="bg-teal-50 p-4 rounded-xl text-teal-800 text-sm leading-relaxed">
                            <p>{__('breathing_desc')}</p>
                            <p className="font-bold mt-2">{__('breathing_desc2')}</p>
                        </div>
                        <button onClick={startBreathing} className="w-full py-4 bg-teal-600 text-white rounded-2xl text-lg font-bold shadow-lg shadow-teal-200 active:scale-95 transition">{__('breathing_start')}</button>
                    </div>
                ) : (
                    <div className="py-8 relative flex flex-col items-center">
                        <div className={`w-40 h-40 rounded-full flex items-center justify-center border-8 border-teal-100 transition-all duration-[4000ms] ${step === 1 ? 'scale-110 bg-teal-50 border-teal-200' : step === 3 ? 'scale-90 bg-white border-teal-50' : 'scale-100 bg-teal-50'}`}>
                            <span className="text-6xl font-bold text-teal-600 font-mono">{timer}</span>
                        </div>
                        <div className="mt-8 space-y-2">
                            <p className="text-2xl font-bold text-teal-800 transition-all">
                                {step === 1 && __("inhale")}
                                {step === 2 && __("hold")}
                                {step === 3 && __("exhale")}
                            </p>
                        </div>
                    </div>
                )}
                <button onClick={onClose} className="mt-8 text-gray-400 text-sm underline">{__('breathing_quit')}</button>
            </div>
        </div>
    );
};

// --- 12. 目标进度 ---
window.GoalProgress = ({ streak, startDate, settings = {} }) => {
    const { Trophy } = window; 
    const __ = window.__ || (k => k);
    
    // 获取里程碑列表
    const milestones = useMemo(() => {
        const base = [7, 14, 21, 28];
        const hundred = Math.floor(streak / 100) * 100;
        const next100 = hundred + 100;
        const next200 = hundred + 200;
        const next300 = hundred + 300;
        
        // 合併里程碑
        const all = [...base, next100, next200, next300];
        // 加上自定義目標
        if (settings.custom_goals) {
            const customs = settings.custom_goals.split(',').map(s => parseInt(s.trim())).filter(n => !isNaN(n));
            all.push(...customs);
        }
        // 去重並排序
        return [...new Set(all)].sort((a, b) => a - b);
    }, [streak, settings.custom_goals]);

    // 過濾出最近的 4 個短中里程碑 (顯示圓圈)
    const shortTermGoals = useMemo(() => {
        // 找到第一個未完成的目標索引
        let nextIdx = milestones.findIndex(g => g > streak);
        if (nextIdx === -1) nextIdx = milestones.length - 1;
        
        // 取前後範圍
        let start = Math.max(0, nextIdx - 1);
        let end = Math.min(milestones.length, start + 4);
        if (end - start < 4) start = Math.max(0, end - 4);
        
        return milestones.slice(start, end);
    }, [milestones, streak]);

    // 過濾出長遠目標 (顯示進度條)
    const longTermGoals = useMemo(() => {
        return milestones.filter(g => g >= 100 && !shortTermGoals.includes(g)).slice(0, 3);
    }, [milestones, shortTermGoals]);

    const getTargetDateParts = (daysToAdd) => {
        if (!startDate) return { year: '----', date: '--/--', remaining: 0 };
        const [y, m, d] = startDate.split('-').map(Number);
        const target = new Date(y, m - 1, d); 
        target.setDate(target.getDate() + daysToAdd);
        const now = new Date();
        now.setHours(0,0,0,0);
        const diffTime = target - now;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        const remaining = diffDays > 0 ? diffDays : 0;
        return { year: target.getFullYear(), date: `${target.getMonth() + 1}/${target.getDate()}`, remaining: remaining };
    };

    return (
        <div className="bg-white/95 backdrop-blur-xl rounded-3xl p-6 shadow-xl text-gray-800 border border-white/50 w-full max-w-md mt-4 group">
            <div className="flex items-center justify-between mb-6">
                <div className="flex items-center gap-3">
                    <div className="p-2.5 bg-yellow-400 text-white rounded-xl shadow-lg shadow-yellow-100 animate-pulse-slow">
                        <Trophy size={20}/>
                    </div>
                    <div>
                        <span className="font-bold text-gray-800 text-lg block leading-tight">{__('goal_title')}</span>
                        <span className="text-[10px] text-gray-400 uppercase tracking-widest">{__('goal_path_subtitle')}</span>
                    </div>
                </div>
                {streak >= 100 && (
                    <div className="flex items-center gap-1 bg-yellow-50 px-3 py-1 rounded-full border border-yellow-100">
                        <span className="text-lg">🏅</span>
                        <span className="text-[10px] font-bold text-yellow-700">{Math.floor(streak/100)} {__('goal_badge_level')}</span>
                    </div>
                )}
            </div>

            <div className="grid grid-cols-4 gap-4 mb-8">
                {shortTermGoals.map(goal => {
                    const percent = Math.min(100, (streak / goal) * 100);
                    const isCompleted = streak >= goal;
                    const { year, date, remaining } = getTargetDateParts(goal);
                    return (
                        <div key={goal} className="flex flex-col items-center group/item">
                            <div className="relative w-14 h-14 flex items-center justify-center mb-2">
                                <svg className="w-full h-full transform -rotate-90">
                                    <circle cx="28" cy="28" r="24" stroke="#f1f5f9" strokeWidth="4" fill="none" />
                                    <circle 
                                        cx="28" cy="28" r="24" 
                                        stroke={isCompleted ? "#14b8a6" : "#2dd4bf"} 
                                        strokeWidth="4" fill="none" 
                                        strokeDasharray="150.8" 
                                        strokeDashoffset={150.8 - (150.8 * percent) / 100} 
                                        className="transition-all duration-1000 ease-out" 
                                        strokeLinecap="round"
                                    />
                                </svg>
                                <div className={`absolute flex flex-col items-center justify-center transition-transform duration-300 ${isCompleted ? 'scale-110' : 'group-hover/item:scale-110'}`}>
                                    {isCompleted ? (
                                        <div className="text-teal-500"><window.CheckIcon size={18}/></div>
                                    ) : (
                                        <span className="text-sm font-black text-gray-700 font-mono">{goal}</span>
                                    )}
                                </div>
                            </div>
                            <span className={`text-[9px] font-bold uppercase tracking-tighter ${isCompleted ? 'text-teal-600' : 'text-gray-400'}`}>
                                {goal} {__('goal_short')}
                            </span>
                            <div className="flex flex-col items-center mt-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <span className="text-[8px] text-gray-400 font-mono">{year}</span>
                                <span className="text-[9px] text-teal-600/80 font-bold">{date}</span>
                            </div>
                        </div>
                    );
                })}
            </div>

            <div className="space-y-5">
                {longTermGoals.map(goal => {
                    const percent = Math.min(100, (streak / goal) * 100);
                    const isCompleted = streak >= goal;
                    const { year, date, remaining } = getTargetDateParts(goal);
                    return (
                        <div key={goal} className="relative">
                            <div className="flex justify-between items-end mb-1.5">
                                <div className="flex items-center gap-2">
                                    <span className={`text-xs font-black ${isCompleted ? 'text-teal-600' : 'text-gray-600'}`}>{goal}{__('goal_long')}</span>
                                    {isCompleted && <span className="text-[10px] bg-teal-50 text-teal-600 px-1.5 py-0.5 rounded-md font-bold">{__('goal_achieved')}</span>}
                                </div>
                                <span className="text-[10px] font-mono font-bold text-teal-600">{percent.toFixed(1)}%</span>
                            </div>
                            <div className="h-2.5 w-full bg-slate-100 rounded-full overflow-hidden relative shadow-inner">
                                <div 
                                    className={`h-full bg-gradient-to-r ${isCompleted ? 'from-teal-500 to-emerald-500' : 'from-teal-400 to-teal-600'} rounded-full transition-all duration-1000 ease-out relative`} 
                                    style={{ width: `${percent}%` }}
                                >
                                    {!isCompleted && <div className="absolute inset-0 bg-white/20 animate-shimmer" style={{ backgroundSize: '20px 20px', backgroundImage: 'linear-gradient(45deg,rgba(255,255,255,.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,.15) 50%,rgba(255,255,255,.15) 75%,transparent 75%,transparent)' }}></div>}
                                </div>
                            </div>
                            <div className="flex justify-between items-center mt-1.5">
                                {!isCompleted ? (
                                    <span className="text-[9px] text-orange-500 font-bold flex items-center gap-1">
                                        <span className="w-1 h-1 bg-orange-400 rounded-full animate-ping"></span>
                                        {remaining} {__('goal_remain')}
                                    </span>
                                ) : <div />}
                                <span className="text-[9px] text-gray-400 font-medium">
                                    {__('goal_expected')}: <span className="font-mono">{year}/{date}</span>
                                </span>
                            </div>
                        </div>
                    );
                })}
            </div>
            
            <div className="mt-8 pt-4 border-t border-slate-50">
                <div className="flex items-center justify-center gap-2 text-[10px] text-red-400 font-bold bg-red-50/50 py-2 rounded-xl border border-red-100/50">
                    <window.ShieldAlert size={12} />
                    <span>{__('goal_warn')}</span>
                </div>
            </div>
        </div>
    );
};

// --- 15. 自定義目標專用模塊 ---
window.CustomMilestonesPanel = ({ streak, settings = {} }) => {
    const __ = window.__ || (k => k);
    const milestones = useMemo(() => {
        if (!settings.custom_goals) return [];
        return settings.custom_goals
            .split(',')
            .map((s) => parseInt(s.trim(), 10))
            .filter((n) => !Number.isNaN(n) && n > 0)
            .sort((a, b) => a - b);
    }, [settings.custom_goals]);

    if (milestones.length === 0) return null;

    return (
        <div className="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm w-full mt-4">
            <div className="flex items-center justify-between mb-3">
                <span className="font-bold text-gray-700 text-sm">{__('custom_milestones_title')}</span>
                <span className="text-[10px] text-gray-400">{__('custom_milestones_desc')}</span>
            </div>
            <div className="space-y-2">
                {milestones.map((days) => {
                    const done = streak >= days;
                    const progress = Math.min(100, (streak / days) * 100);
                    return (
                        <div key={days} className="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
                            <div className="flex items-center justify-between text-xs mb-1.5">
                                <span className="font-bold text-gray-700">{days} {__('goal_short')}</span>
                                <span className={done ? 'text-teal-600 font-bold' : 'text-gray-500'}>
                                    {done ? __('goal_achieved') : `${Math.max(0, days - streak)} ${__('goal_remain')}`}
                                </span>
                            </div>
                            <div className="h-2 bg-slate-200 rounded-full overflow-hidden">
                                <div
                                    className={`h-full rounded-full ${done ? 'bg-teal-500' : 'bg-blue-500'}`}
                                    style={{ width: `${progress}%` }}
                                ></div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
};

// === 模块索引 ===
// 本文件: 核心 UI 组件 (图标、日历、弹窗、植物、目标、随笔)
// js/analysis.js      — AnalysisDashboard (深度洞察报告)
// js/record-browser.js — RecordBrowser (记录浏览器: 日历检索+搜索)
// js/hooks.js          — useAppData, useCalendarLogic 等数据钩子
// js/app.js            — App 主组件与渲染入口
// 加载顺序: components.js → analysis.js → record-browser.js → hooks.js → app.js

