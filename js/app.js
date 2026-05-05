// js/app.js
// 完整逻辑控制层 v5.26 (Timezone Fix)

const { useState, useEffect, useMemo, useCallback } = React;

// 1. 从全局引入所有 UI 组件和钩子
const {
    CalendarIcon, Activity, EyeOff, ShieldAlert, Zap, Droplets, LogOut, ChevronLeft, ChevronRight,
    PanicModal, GoalProgress, ActionButton, PlantStage, DualStats, CalendarDay, ConfirmModal,
    RelapseReasonModal, EnergyInput, AnalysisDashboard, DayPopover, DailyNote, AchievementModal,
    NoteHistory, CustomMilestonesPanel, RecordBrowser,
    useAppData, useCalendarLogic, useDailyQuote, useMediaQuery
} = window;

// --- Config ---
const AppConfig = window.SouleanConfig || { isLoggedIn: false, isPublicMode: false };

// --- 辅助：获取本地日期字符串 (YYYY-MM-DD) ---
// [Fix] 解决 UTC 时间导致的日期错位问题

// --- 组件 ---
const _t = (key) => window.__ ? window.__(key) : key;

const QuoteCard = () => {
    const quote = useDailyQuote();
    return <div className="quote-card"><p className="quote-text">{quote || "自律即自由。"}</p></div>;
};

const PublicAlertModal = ({ onClose }) => (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div className="absolute inset-0 bg-teal-900/40 backdrop-blur-sm transition-opacity" onClick={onClose}></div>
        <div className="relative bg-white rounded-2xl p-6 w-full max-w-sm shadow-2xl animate-fade-in border border-white/50">
            <h3 className="text-xl font-bold text-gray-800 mb-2">{_t('public_alert_title')}</h3>
            <p className="text-gray-600 mb-8 text-sm leading-relaxed opacity-80">{_t('public_alert_desc')}</p>
            <div className="flex gap-3 justify-end">
                <button onClick={onClose} className="px-6 py-2.5 bg-teal-600 text-white font-bold rounded-xl shadow-lg transition-all active:scale-95 text-sm">{_t('public_alert_btn')}</button>
            </div>
        </div>
    </div>
);

const LoadingActionButton = ({ icon, label, color, textColor="text-white", onClick, isLoading }) => (
    <button onClick={onClick} disabled={isLoading} className={`action-tile group w-full transition-opacity ${isLoading?'opacity-50 cursor-not-allowed':''}`}>
        <div className={`w-14 h-14 rounded-2xl ${color} ${textColor} flex items-center justify-center shadow-lg transition-transform ${!isLoading ? 'group-hover:scale-105 group-active:scale-95' : ''} border-2 border-white/20 relative`}>
            {isLoading ? (
                <svg className="animate-spin h-6 w-6" viewBox="0 0 24 24" fill="none"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
            ) : icon}
        </div>
        <span className="text-xs font-bold text-slate-700 group-hover:text-slate-900">{label}</span>
    </button>
);

const DayDetailsModalWithDelete = ({ dateStr, logs, onClose, onDelete }) => {
    const { Zap, EyeOff, Activity, CloseIcon, TrashIcon, Droplets } = window;
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" style={{zIndex: 60}}>
            <div className="absolute inset-0 bg-teal-900/30 backdrop-blur-sm transition-opacity" onClick={onClose}></div>
            <div className="relative bg-white rounded-3xl p-5 w-full max-w-[300px] shadow-2xl animate-fade-in border border-white/60">
                <button onClick={onClose} className="absolute top-3 right-3 text-gray-400 p-1.5"><CloseIcon size={14}/></button>
                <div className="mb-4 px-1"><h3 className="text-lg font-bold text-gray-800">{dateStr}</h3></div>
                <div className="space-y-2 max-h-[50vh] overflow-y-auto pr-1">
                    {logs.length===0 ? (
                        <div className="text-center py-8 text-gray-300 flex flex-col items-center border-2 border-dashed border-gray-100 rounded-xl">
                            <span className="text-2xl mb-1 opacity-50">🪷</span><span className="text-xs font-medium">{_t('no_log')}</span>
                        </div>
                    ) : logs.map((type, i) => {
                        let icon, label, bgClass, textClass;
                        if (type === 'relapse') { icon=<Zap size={16} fill="currentColor"/>; label=_t('relapse_btn'); bgClass='bg-gray-100'; textClass='text-gray-600'; }
                        else if (type === 'sex') { icon=<span className="text-base">🛏️</span>; label=_t('sex_btn'); bgClass='bg-slate-100'; textClass='text-slate-700'; }
                        else if (type === 'emission') { icon=<Droplets size={16}/>; label=_t('emission_btn'); bgClass='bg-indigo-50'; textClass='text-indigo-700'; }
                        else if (type === 'porn') { icon=<EyeOff size={16}/>; label=_t('porn_btn'); bgClass='bg-amber-50'; textClass='text-amber-700'; }
                        else { icon=<Activity size={16}/>; label=_t('urge_btn'); bgClass='bg-orange-50'; textClass='text-orange-700'; }
                        return (
                            <div key={i} className={`flex items-center justify-between px-4 py-3 rounded-xl ${bgClass} ${textClass}`}>
                                <div className="flex items-center gap-3">{icon}<span className="font-bold text-sm">{label}</span></div>
                                {AppConfig.isLoggedIn && <button onClick={()=>onDelete(dateStr,type)} className="p-1.5 bg-white/50 rounded-lg hover:text-red-500"><TrashIcon size={14}/></button>}
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
};

// 大脑修复可视化组件 (SVG)
const BrainVisualization = ({ score }) => {
    let themeColor = "#ef4444"; 
    let stateKey = 'neural_unstable';
    if (score > 60) { themeColor = "#eab308"; stateKey = 'neural_healing'; }
    if (score > 90) { themeColor = "#2dd4bf"; stateKey = 'neural_rewired'; }
    const opacity = Math.max(0.2, Math.min(1, score / 100));

    return (
        <div className="relative w-48 h-48 flex items-center justify-center select-none cursor-default">
            <svg viewBox="0 0 200 200" className="w-full h-full drop-shadow-xl overflow-visible">
                <defs>
                    <filter id="glow-brain" x="-50%" y="-50%" width="200%" height="200%">
                        <feGaussianBlur stdDeviation="4" result="coloredBlur" />
                        <feMerge><feMergeNode in="coloredBlur" /><feMergeNode in="SourceGraphic" /></feMerge>
                    </filter>
                </defs>
                <path d="M100 170 C 60 170, 30 140, 30 100 C 30 50, 60 30, 100 30 C 140 30, 170 50, 170 100 C 170 140, 140 170, 100 170 Z" fill="none" stroke="rgba(255,255,255,0.15)" strokeWidth="2" />
                <path d="M100 35 V 165" stroke="rgba(255,255,255,0.1)" strokeWidth="1" strokeDasharray="4 3" />
                <circle cx="100" cy="50" r={score > 80 ? 6 : 4} fill={themeColor} filter={score > 80 ? "url(#glow-brain)" : ""} opacity={opacity} className="transition-all duration-1000" />
                <circle cx="70" cy="100" r="4" fill={themeColor} opacity={opacity * 0.8} className="transition-all duration-1000" />
                <circle cx="130" cy="100" r="4" fill={themeColor} opacity={opacity * 0.8} className="transition-all duration-1000" />
                <circle cx="85" cy="130" r="3" fill={themeColor} opacity={opacity * 0.6} />
                <circle cx="115" cy="130" r="3" fill={themeColor} opacity={opacity * 0.6} />
                <g stroke={themeColor} strokeWidth="1.5" fill="none" opacity={opacity}>
                    <path d="M100 50 Q 70 70 70 100 T 85 130" strokeDasharray="4 2" />
                    <path d="M100 50 Q 130 70 130 100 T 115 130" strokeDasharray="4 2" />
                    <path d="M70 100 Q 100 120 130 100" strokeWidth="1" opacity="0.5" />
                </g>
                {score > 60 && <circle r="2" fill="white"><animateMotion dur="2s" repeatCount="indefinite" path="M100 50 Q 70 70 70 100 T 85 130" /></circle>}
                {score > 60 && <circle r="2" fill="white"><animateMotion dur="2.5s" repeatCount="indefinite" path="M100 50 Q 130 70 130 100 T 115 130" /></circle>}
            </svg>
            <div className="absolute bottom-10 left-0 right-0 text-center">
                <span className="text-[9px] font-bold tracking-[0.2em] text-white/60">{_t('neural_state')}</span>
                <div style={{ color: themeColor }} className="text-xs font-bold mt-0.5 transition-colors duration-1000 drop-shadow-md">{_t(stateKey)}</div>
            </div>
        </div>
    );
};

// --- App Component ---
const App = () => {
    const [showPanic, setShowPanic] = useState(false);
    const [showPublicAlert, setShowPublicAlert] = useState(false);
    const [selectedDetailsDate, setSelectedDetailsDate] = useState(null);
    const [confirmModal, setConfirmModal] = useState(null);
    const [relapseReasonModal, setRelapseReasonModal] = useState(false);
    const [visualMode, setVisualMode] = useState('plant');
    const [theme, setTheme] = useState(() => {
        try {
            const saved = localStorage.getItem('soulean_theme');
            return saved === 'dark' ? 'dark' : 'light';
        } catch (e) {
            return 'light';
        }
    });
    const [sendingReport, setSendingReport] = useState(false);
    const [reportMsg, setReportMsg] = useState('');
    const [achievementDays, setAchievementDays] = useState(null);
    const [langDropdownOpen, setLangDropdownOpen] = useState(false);
    const lastAchievementRef = React.useRef(null);
    
    const isDesktop = useMediaQuery('(min-width: 1024px)');
    
    // [Merged] 解构出 settings
    const { startDate, score, logs, analysis, settings, loading, submitting, submitLog, deleteLog, deleteNote, logout, fetchData } = useAppData();
    const { viewDate, changeMonth, calendarGrid, relapseStreak, pornStreak, todayDate } = useCalendarLogic(startDate, logs);

    // [Fix] 使用本地时间代替 UTC 时间
    const todayStr = getLocalTodayStr();
    const todayEnergy = analysis?.energy_logs?.[todayStr];
    const allNotes = analysis?.daily_notes || {};

    const toggleVisualMode = () => setVisualMode(prev => prev === 'plant' ? 'brain' : 'plant');

    const handleLanguageChange = async (newLang) => {
        try {
            await fetch('api.php?action=set_language', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': AppConfig.csrfToken
                },
                body: JSON.stringify({ lang: newLang })
            });
            window.location.reload();
        } catch (err) {
            console.error('Failed to change language', err);
        }
    };

    const toggleTheme = () => setTheme((prev) => (prev === 'dark' ? 'light' : 'dark'));

     const getPointDeduction = (type) => {
        const pts = settings?.points || {};
        return pts[type] ?? { urge: 0.01, porn: 1.0, emission: 2.0, sex: 3.0, relapse: 3.0 }[type] ?? 0;
    };

     const handleLogClick = (type) => {
        if (!AppConfig.isLoggedIn) { setShowPublicAlert(true); return; }
        if (submitting) return;

        if (type === 'relapse') {
            setRelapseReasonModal(true);
        } else if (type === 'sex') {
            setConfirmModal({ type, title: _t('confirm_sex_title'), message: _t('confirm_sex_msg').replace('{pts}', getPointDeduction('sex')) });
        } else if (type === 'porn') {
            setConfirmModal({ type, title: _t('confirm_porn_title'), message: _t('confirm_porn_msg').replace('{pts}', getPointDeduction('porn')) });
        } else if (type === 'emission') {
            setConfirmModal({ type, title: _t('confirm_emission_title'), message: _t('confirm_emission_msg').replace('{pts}', getPointDeduction('emission')) });
        } else {
            submitLog(type);
        }
    };

    const handleRelapseConfirm = (reason) => {
        submitLog('relapse', { reason });
        setRelapseReasonModal(false);
    };

    const handleEnergySave = (level) => {
        if (!AppConfig.isLoggedIn) return;
        submitLog('energy', { energy: level });
    };

    const handleConfirm = () => {
        if (confirmModal) {
            if (confirmModal.isNoteDelete) deleteNote(confirmModal.date);
            else if (confirmModal.isDelete) deleteLog(confirmModal.date, confirmModal.logType);
            else submitLog(confirmModal.type);
            setConfirmModal(null);
        }
    };

    const handleDateClick = (d) => setSelectedDetailsDate(selectedDetailsDate === d ? null : d);
    const handleDeleteLog = (d, t) => setConfirmModal({ isDelete:true, date:d, logType:t, title:_t('confirm_delete_title'), message:_t('confirm_delete_msg'), type:'relapse' });
    const handleDeleteNote = (d) => setConfirmModal({ isNoteDelete:true, date:d, title:_t('note_delete_title'), message:_t('note_delete_msg'), type:'relapse' });
    const goToAdmin = () => { window.location.href = 'admin.php'; };
    const goToLogin = () => { window.location.href = 'login.php'; };

    const handleSendReport = async () => {
        if (sendingReport) return;
        setSendingReport(true);
        setReportMsg('');
        try {
            const res = await fetch('api.php?action=send_report', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': AppConfig.csrfToken
                }
            });
            if (res.status === 400) {
                setReportMsg(_t('report_no_email'));
            } else if (!res.ok) {
                setReportMsg(_t('report_fail'));
            } else {
                setReportMsg(_t('report_sent'));
            }
        } catch (err) {
            setReportMsg(_t('report_fail'));
        }
        setSendingReport(false);
        setTimeout(() => setReportMsg(''), 5000);
    };

    const handleSaveNote = async (noteText) => {
        try {
            const res = await fetch('api.php?action=save_note', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': AppConfig.csrfToken
                },
                body: JSON.stringify({ note: noteText })
            });
            if (res.ok) await fetchData();
        } catch (err) { /* silently fail for notes */ }
    };

    useEffect(() => {
        document.body.classList.toggle('theme-dark', theme === 'dark');
        try {
            localStorage.setItem('soulean_theme', theme);
        } catch (e) {
            // ignore storage errors
        }
    }, [theme]);

    const formatCalendarTitle = () => {
        const month = calendarGrid.month + 1;
        const year = calendarGrid.year;
        const format = _t('calendar_header');
        if (format && format !== 'calendar_header') {
            return format.replace('{year}', year).replace('{month}', month);
        }
        return `${year} ${_t('calendar_year')} ${month} ${_t('calendar_title')}`;
    };

    // 里程碑检测 — 当 streak 刚好命中关键里程碑时弹窗
    useEffect(() => {
        const defaultMilestones = [7, 14, 21, 28, 50, 100, 150, 200, 300, 365];
        const customMilestones = String(settings?.custom_goals || '')
            .split(',')
            .map((s) => parseInt(s.trim(), 10))
            .filter((n) => !Number.isNaN(n) && n > 0);
        const milestones = [...new Set([...defaultMilestones, ...customMilestones])];
        if (milestones.includes(relapseStreak) && lastAchievementRef.current !== relapseStreak) {
            lastAchievementRef.current = relapseStreak;
            setAchievementDays(relapseStreak);
        }
    }, [relapseStreak, settings?.custom_goals]);

    const todayNote = analysis?.daily_notes?.[todayStr] || '';

    if (loading) return <div className="flex h-screen flex-col items-center justify-center text-white"><div className="spinner mb-4"></div><p className="opacity-80 text-sm">{_t('sync_data')}</p></div>;

    return (
        <div className="app-container">
            {showPanic && <PanicModal onClose={() => setShowPanic(false)} />}
            {showPublicAlert && <PublicAlertModal onClose={() => setShowPublicAlert(false)} />}
            
            <RelapseReasonModal 
                isOpen={relapseReasonModal} 
                onClose={() => setRelapseReasonModal(false)} 
                onConfirm={handleRelapseConfirm} 
            />

            <ConfirmModal 
                isOpen={!!confirmModal} 
                title={confirmModal?.title} 
                message={confirmModal?.message} 
                type={confirmModal?.type}
                onConfirm={handleConfirm} 
                onCancel={() => setConfirmModal(null)} 
            />

            <AchievementModal 
                isOpen={!!achievementDays}
                days={achievementDays || 0}
                onClose={() => setAchievementDays(null)}
            />
            
            {!isDesktop && selectedDetailsDate && (
                <DayDetailsModalWithDelete 
                    dateStr={selectedDetailsDate}
                    logs={logs[selectedDetailsDate] || []}
                    onClose={() => setSelectedDetailsDate(null)}
                    onDelete={handleDeleteLog}
                />
            )}

            <div className="main-card">
                <div className="layout-section left-section">
                    <div className="header-nav flex-col items-start gap-4">
                        <div className="flex flex-col">
                            <div className="flex items-center gap-2">
                                <Droplets size={20} className="text-teal-100" />
                                <span className="font-bold text-2xl tracking-tight text-white">Soulean</span>
                            </div>
                            <span className="text-teal-100 text-xs tracking-widest opacity-80 pl-7">{_t('app_title')}</span>
                        </div>

                        <div className="flex gap-3 mt-1">
                            <button onClick={() => setShowPanic(true)} className="sos-btn bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-full text-xs font-bold shadow-lg flex items-center gap-1 transition-transform active:scale-95">
                                <Zap size={12} fill="currentColor"/> SOS
                            </button>
                            
                            {AppConfig.isLoggedIn && (
                                <button 
                                    onClick={handleSendReport} 
                                    disabled={sendingReport}
                                    className="px-3 py-1.5 bg-teal-600 hover:bg-teal-700 text-white rounded-full text-xs font-bold shadow-lg flex items-center gap-1 transition-transform active:scale-95 disabled:opacity-60"
                                    title={_t('report_send_btn')}
                                >
                                    {sendingReport ? (
                                        <span className="animate-spin inline-block w-3 h-3 border-2 border-white/30 border-t-white rounded-full"></span>
                                    ) : (
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                                    )}
                                    <span>{sendingReport ? _t('report_sending') : _t('report_send_btn')}</span>
                                </button>
                            )}

                            <button
                            onClick={toggleTheme}
                            className="px-3 py-1.5 bg-white/10 hover:bg-white/20 text-white rounded-full text-xs font-bold shadow-lg transition-transform active:scale-95"
                            title={_t('theme_toggle')}
                        >
                            {theme === 'dark' ? _t('theme_light') : _t('theme_dark')}
                        </button>

                        <div className="relative">
                            <button
                                onClick={() => setLangDropdownOpen(!langDropdownOpen)}
                                className="px-3 py-1.5 bg-white/10 hover:bg-white/20 text-white rounded-full text-xs font-bold shadow-lg transition-transform active:scale-95 flex items-center gap-1"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                <span>{settings?.language === 'en' ? 'EN' : (settings?.language === 'zh-TW' ? '繁' : '简')}</span>
                            </button>
                            {langDropdownOpen && (
                                <>
                                    <div className="fixed inset-0 z-40" onClick={() => setLangDropdownOpen(false)}></div>
                                    <div className="absolute top-full left-0 mt-2 w-28 bg-teal-800 rounded-xl overflow-hidden border border-teal-600/30 z-50 shadow-xl">
                                        <button onClick={() => { handleLanguageChange('zh-CN'); setLangDropdownOpen(false); }} className="w-full px-3 py-2.5 text-left text-xs text-white hover:bg-white/10 transition">简体中文</button>
                                        <button onClick={() => { handleLanguageChange('zh-TW'); setLangDropdownOpen(false); }} className="w-full px-3 py-2.5 text-left text-xs text-white hover:bg-white/10 transition">繁體中文</button>
                                        <button onClick={() => { handleLanguageChange('en'); setLangDropdownOpen(false); }} className="w-full px-3 py-2.5 text-left text-xs text-white hover:bg-white/10 transition">English</button>
                                    </div>
                                </>
                            )}
                        </div>
                            
                            {AppConfig.isLoggedIn ? (
                                <>
                                    <button onClick={goToAdmin} title={_t('admin_btn')} className="icon-btn bg-white/10 hover:bg-white/20 text-white p-1.5 rounded-full backdrop-blur-sm transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.47a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                    <button onClick={logout} title={_t('logout_btn')} className="icon-btn bg-white/10 hover:bg-white/20 text-white p-1.5 rounded-full backdrop-blur-sm transition">
                                        <LogOut size={14} />
                                    </button>
                                </>
                            ) : (
                                <button onClick={goToLogin} title={_t('login_btn')} className="px-3 py-1.5 bg-teal-800/50 hover:bg-teal-800/70 text-white text-xs font-bold rounded-full backdrop-blur-sm transition border border-teal-600/30">
                                    {_t('login_btn')}
                                </button>
                            )}
                        </div>
                    </div>

                    <div className="circle-container group">
                        <button 
                            onClick={toggleVisualMode} 
                            className="absolute top-0 right-0 z-30 p-2 text-white/50 hover:text-white hover:bg-white/10 rounded-full transition-all duration-300 transform hover:scale-110"
                            title={visualMode === 'plant' ? "切换至神经修复视图" : "切换至植物生长视图"}
                        >
                            {visualMode === 'plant' ? (
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M9.5 2A2.5 2.5 0 0 1 12 4.5v15a2.5 2.5 0 0 1-4.96.44 2.5 2.5 0 0 1-2.96-3.08 3 3 0 0 1-.34-5.58 2.5 2.5 0 0 1 1.32-4.24 2.5 2.5 0 0 1 1.98-3A2.5 2.5 0 0 1 9.5 2Z"/><path d="M14.5 2A2.5 2.5 0 0 0 12 4.5v15a2.5 2.5 0 0 0 4.96.44 2.5 2.5 0 0 0 2.96-3.08 3 3 0 0 0 .34-5.58 2.5 2.5 0 0 0-1.32-4.24 2.5 2.5 0 0 0-1.98-3A2.5 2.5 0 0 0 14.5 2Z"/></svg>
                            ) : (
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M7 17a5 5 0 0 1 5-5a5 5 0 0 1 5 5"/><path d="M12 22v-9"/><path d="M8 8a4 4 0 0 1 4 4"/><path d="M16 8a4 4 0 0 0-4 4"/></svg>
                            )}
                        </button>

                        <div className="circle-glow"></div>
                        <div className="circle-body relative">
                            <div className={`absolute inset-0 transition-opacity duration-500 ${visualMode === 'plant' ? 'opacity-100' : 'opacity-20'}`}>
                                <div className="absolute bottom-0 left-0 right-0 bg-[#4caebf] opacity-80 transition-all duration-1000 ease-in-out" style={{ height: `${Math.min(100, Math.max(0, score))}%` }}></div>
                                <div className="absolute bottom-0 left-0 right-0 bg-[#a5f3fc] opacity-30 transition-all duration-1000 ease-in-out wave-animation" style={{ height: `${Math.min(100, Math.max(0, score - 5))}%` }}></div>
                            </div>

                            <div className="circle-content w-full h-full flex flex-col items-center justify-center pt-6">
                                <div className="mb-2 filter drop-shadow-lg transition-all duration-500 cursor-default select-none relative w-full flex justify-center">
                                    {visualMode === 'plant' ? (
                                        <div className="animate-fade-in"><PlantStage score={score} /></div>
                                    ) : (
                                        <div className="animate-fade-in"><BrainVisualization score={score} /></div>
                                    )}
                                </div>
                                
                                <div className="text-white drop-shadow-md flex flex-col items-center w-full z-10">
                                    <DualStats relapseStreak={relapseStreak} pornStreak={pornStreak} score={score} />
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {AppConfig.isLoggedIn && (
                        <div className="w-full max-w-[320px] mb-6">
                            <EnergyInput todayEnergy={todayEnergy} onSave={handleEnergySave} />
                        </div>
                    )}

                    {AppConfig.isLoggedIn && (
                        <>
                            <DailyNote note={todayNote} onSave={handleSaveNote} />
                            <NoteHistory notes={allNotes} onDelete={handleDeleteNote} />
                        </>
                    )}

                    {reportMsg && (
                        <div className={`w-full max-w-[320px] text-center text-xs font-bold py-2 rounded-xl animate-fade-in ${
                            reportMsg === _t('report_sent') ? 'bg-teal-50 text-teal-600' : 'bg-red-50 text-red-500'
                        }`}>
                            {reportMsg}
                        </div>
                    )}

                    <QuoteCard />
                </div>

                <div className="layout-section right-section">
                    <div className="right-content-wrapper">
                        <div className="action-grid">
                            <LoadingActionButton icon={<Activity size={24} />} label={_t('urge_btn')} color="bg-orange-500" isLoading={submitting} onClick={() => handleLogClick('urge')} />
                            <LoadingActionButton icon={<EyeOff size={24} />} label={_t('porn_btn')} color="bg-yellow-500" textColor="text-yellow-900" isLoading={submitting} onClick={() => handleLogClick('porn')} />
                            <LoadingActionButton icon={<span className="text-xl leading-none">🛏️</span>} label={_t('sex_btn')} color="bg-slate-600" isLoading={submitting} onClick={() => handleLogClick('sex')} />
                            {settings?.gender !== 'female' && (
                                <LoadingActionButton icon={<Droplets size={24} />} label={_t('emission_btn')} color="bg-indigo-500" isLoading={submitting} onClick={() => handleLogClick('emission')} />
                            )}
                            <LoadingActionButton icon={<ShieldAlert size={24} />} label={_t('relapse_btn')} color="bg-gray-800" isLoading={submitting} onClick={() => handleLogClick('relapse')} />
                        </div>

                        {/* [Merged] 传递 settings 给 AnalysisDashboard */}
                        <AnalysisDashboard analysisData={analysis} score={score} settings={settings} logs={logs} startDate={startDate} />

                        <RecordBrowser
                            logs={logs}
                            notes={allNotes}
                            relapseRecords={analysis?.relapse_records || []}
                            energyLogs={analysis?.energy_logs || {}}
                            onDelete={handleDeleteLog}
                            isLoggedIn={AppConfig.isLoggedIn}
                        />

                        <GoalProgress streak={relapseStreak} startDate={startDate} settings={settings} />
                        <CustomMilestonesPanel streak={relapseStreak} settings={settings} />

                        <div className="calendar-card mt-6">
                            <div className="flex items-center justify-between mb-4">
                                <div className="flex items-center gap-2">
                                    <div className="p-2 bg-teal-100 rounded-lg text-teal-700"><CalendarIcon size={18}/></div>
                                    <span className="font-bold text-gray-700 text-lg">{formatCalendarTitle()}</span>
                                </div>
                                <div className="flex gap-1">
                                    <button onClick={() => changeMonth(-1)} className="p-1 hover:bg-gray-100 rounded-full transition"><ChevronLeft size={20} className="text-gray-500"/></button>
                                    <button onClick={() => changeMonth(1)} className="p-1 hover:bg-gray-100 rounded-full transition"><ChevronRight size={20} className="text-gray-500"/></button>
                                </div>
                            </div>
                            <div className="grid grid-cols-7 gap-y-2 gap-x-1 text-center">
                                {[_t('weekday_sun'),_t('weekday_mon'),_t('weekday_tue'),_t('weekday_wed'),_t('weekday_thu'),_t('weekday_fri'),_t('weekday_sat')].map((d, i) => (
                                    <div key={i} className="text-gray-300 text-[10px] font-bold">{d}</div>
                                ))}
                                {calendarGrid.grid.map((dayNum, i) => {
                                    if (!dayNum) return <div key={i}></div>;
                                    const m = String(calendarGrid.month + 1).padStart(2, '0');
                                    const d = String(dayNum).padStart(2, '0');
                                    const dateStr = `${calendarGrid.year}-${m}-${d}`;
                                    const checkDate = new Date(calendarGrid.year, calendarGrid.month, dayNum); 
                                    const isToday = checkDate.getTime() === todayDate.getTime();
                                    const isFuture = checkDate > todayDate;

                                    return (
                                        <CalendarDay 
                                            key={i} dayNum={dayNum} isToday={isToday} isFuture={isFuture} checkDate={checkDate} now={todayDate}
                                            logs={logs[dateStr] || []}
                                            isDesktop={isDesktop} 
                                            dateStr={dateStr}
                                            isSelected={selectedDetailsDate === dateStr}
                                            onClick={() => handleDateClick(dateStr)}
                                            onClosePopover={() => setSelectedDetailsDate(null)}
                                            onDelete={handleDeleteLog} 
                                        />
                                    );
                                })}
                            </div>
                        </div>

                        <div className="footer-info">&copy; {_t('footer')} &bull; Beta 0.0.1</div>
                    </div>
                </div>
            </div>
        </div>
    );
}

const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(<App />);
