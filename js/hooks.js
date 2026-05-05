// js/hooks.js — Data fetching, calendar logic, and utility hooks
const { useState, useEffect, useMemo, useCallback } = React;

const AppConfig = window.SouleanConfig || { isLoggedIn: false, isPublicMode: false };

const getLocalTodayStr = () => {
    const d = new Date();
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// --- 静态语录 (API 失败时的兜底) ---
const FALLBACK_QUOTES = [
    "冲动只是多巴胺的骗局，它会在 15 分钟内消退。",
    "你不能阻止鸟儿从头顶飞过，但你可以阻止它们在头顶做窝。",
    "真正的自由不是随心所欲，而是主宰自己。",
    "种一棵树最好的时间是十年前，其次是现在。",
    "凡是不能毁灭我的，必将使我强大。",
    "痛苦有两种：一种让你受伤，一种让你改变。",
    "自律即自由。",
    "不要假装努力，结果不会陪你演戏。",
    "凝视深渊过久，深渊将回以凝视。",
    "此时此刻的克制，是未来无限可能的基石。"
];

// --- Hook: 获取每日一句 ---
const useDailyQuote = () => { 
    const [quote, setQuote] = useState("");
    useEffect(() => {
        fetch('https://v1.hitokoto.cn/?c=d&c=k&c=i&encode=json&charset=utf-8')
            .then(res => res.json())
            .then(data => { if (data && data.hitokoto) setQuote(data.hitokoto); else throw new Error("Empty"); })
            .catch(err => {
                const index = Math.floor(Math.random() * FALLBACK_QUOTES.length);
                setQuote(FALLBACK_QUOTES[index]);
            });
    }, []);
    return quote;
}; 

// --- Hook: 响应式布局检测 ---
const useMediaQuery = (query) => {
    const [matches, setMatches] = useState(window.matchMedia(query).matches);
    useEffect(() => {
        const m = window.matchMedia(query);
        const l = () => setMatches(m.matches);
        m.addEventListener('change', l);
        return () => m.removeEventListener('change', l);
    }, [query]);
    return matches;
};

// --- Hook: 核心数据与操作 (Merged) ---
const useAppData = () => {
    const [data, setData] = useState({ 
        startDate: '', score: 80.0, logs: {}, 
        analysis: { relapse_records: [], energy_logs: {} },
        settings: {} 
    });
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);

    const fetchData = useCallback(async () => {
        try {
            const res = await fetch('api.php?action=get_data', { cache: 'no-store' });
            if (!res.ok) { 
                if (res.status === 401) { window.location.href = 'login.php'; return; }
                throw new Error("API Error: " + res.status);
            }
            const json = await res.json();
            if (json.error === 'Unauthorized') { window.location.href = 'login.php'; return; }
            
            setData({ 
                startDate: json.start_date, 
                score: Number(json.score), 
                logs: json.logs || {},
                analysis: json.analysis || { relapse_records: [], energy_logs: {} },
                settings: json.settings || {} 
            });
            setLoading(false);
        } catch (err) { 
            console.error("Sync Error", err); 
            setLoading(false); 
        }
    }, []);

    useEffect(() => { fetchData(); }, [fetchData]);

    const submitLog = async (type, extraData = {}) => {
        if (submitting) return;
        setSubmitting(true);
        try {
            const payload = { type, ...extraData };
            const res = await fetch('api.php?action=log', { 
                method: 'POST', 
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': AppConfig.csrfToken
                },
                body: JSON.stringify(payload) 
            });
            
            if (res.status === 403) {
                console.warn("Read-only mode");
            } else if (!res.ok) {
                const errData = await res.json();
                console.error("Log Error:", errData);
            } else {
                await fetchData(); // 成功后刷新
            }
        } catch (err) { console.error(err); } 
        finally { setSubmitting(false); }
    };

    const deleteLog = async (date, type) => {
        if (submitting) return;
        setSubmitting(true);
        try {
            const res = await fetch('api.php?action=delete_log', { 
                method: 'POST', 
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': AppConfig.csrfToken
                },
                body: JSON.stringify({ date, type }) 
            });
            if (res.ok) await fetchData();
            else console.error("删除失败");
        } catch (err) { console.error("网络错误", err); } 
        finally { setSubmitting(false); }
    };

    const deleteNote = async (date) => {
        if (submitting) return;
        setSubmitting(true);
        try {
            const res = await fetch('api.php?action=delete_note', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': AppConfig.csrfToken
                },
                body: JSON.stringify({ date })
            });
            if (res.ok) await fetchData();
            else console.error("删除随笔失败");
        } catch (err) { console.error("网络错误", err); }
        finally { setSubmitting(false); }
    };

    const logout = async () => {
        await fetch('api.php?action=logout', {
            method:'POST',
            headers: { 'X-CSRF-TOKEN': AppConfig.csrfToken }
        }); 
        window.location.href = 'login.php'; 
    };

    return { ...data, loading, submitting, submitLog, deleteLog, deleteNote, logout, fetchData };
};

// --- Hook: 日历计算逻辑 ---
const useCalendarLogic = (startDate, logs) => {
    const [viewDate, setViewDate] = useState(new Date());

    const parseLocalDate = (dateStr) => { 
        if (!dateStr) return new Date(); 
        const [y, m, d] = dateStr.split('-').map(Number); 
        return new Date(y, m - 1, d); 
    };

    const getDaysDiff = (dateStr) => { 
        if (!dateStr) return 0; 
        const start = parseLocalDate(dateStr); 
        const now = new Date(); 
        start.setHours(0,0,0,0); now.setHours(0,0,0,0); 
        const diff = Math.round((now - start) / (1000 * 60 * 60 * 24)); 
        return diff >= 0 ? diff : 0; 
    };
    
    // 计算戒色天数 (距离上一次 'porn' 记录)
    const pornStreak = useMemo(() => {
        const pornDates = Object.keys(logs).filter(d => {
            const entry = logs[d];
            return Array.isArray(entry) && entry.includes('porn');
        });
        if (pornDates.length === 0) return getDaysDiff(startDate);
        pornDates.sort().reverse();
        return getDaysDiff(pornDates[0]);
    }, [logs, startDate]);

    // 计算清心天数 (核心 Streak)
    const relapseStreak = getDaysDiff(startDate);

    const changeMonth = (o) => { const d = new Date(viewDate); d.setMonth(d.getMonth() + o); setViewDate(d); };
    
    const calendarGrid = useMemo(() => {
        const y = viewDate.getFullYear(), m = viewDate.getMonth();
        const days = new Date(y, m + 1, 0).getDate();
        const first = new Date(y, m, 1).getDay();
        const grid = Array(first).fill(null);
        for (let i = 1; i <= days; i++) grid.push(i);
        return { grid, year: y, month: m };
    }, [viewDate]);

    return { viewDate, changeMonth, calendarGrid, relapseStreak, pornStreak, todayDate: new Date(new Date().setHours(0,0,0,0)) };
};

// Expose to global scope for cross-module access
window.useAppData = useAppData;
window.useCalendarLogic = useCalendarLogic;
window.useDailyQuote = useDailyQuote;
window.useMediaQuery = useMediaQuery;
