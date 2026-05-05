// js/analysis.js — Deep Insight Report
const { useState, useEffect, useMemo } = React;

const normalizeReasonKey = (reasonRaw, __) => {
    const value = String(reasonRaw || '').trim().toLowerCase();
    const dict = {
        boredom: 'reason_boredom',
        stress: 'reason_stress',
        insomnia: 'reason_insomnia',
        trigger: 'reason_trigger',
        alone: 'reason_alone',
        habit: 'reason_habit'
    };
    if (dict[value]) return dict[value];
    const localizedMap = {};
    Object.keys(dict).forEach((id) => {
        localizedMap[String(__(dict[id]) || '').trim().toLowerCase()] = dict[id];
    });
    return localizedMap[value] || null;
};

// --- 8. 数据分析图表 (AnalysisDashboard) ---
window.AnalysisDashboard = ({ analysisData, score, settings, logs = {}, startDate = '' }) => {
    const [isModalOpen, setIsModalOpen] = useState(false); 
    const { relapse_records = [], energy_logs = {} } = analysisData;
    const __ = window.__ || (k => k);

    // 获取设置的天数，默认为 7
    const daysToShow = (settings && settings.energy_chart_days) ? parseInt(settings.energy_chart_days) : 7;

    // --- 1. 基础数据计算 ---
    const todayObj = new Date();
    todayObj.setHours(0, 0, 0, 0);
    const sevenDaysAgo = new Date(todayObj);
    sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 6);

    const typeCounts = { urge: 0, porn: 0, relapse: 0, sex: 0, emission: 0 };
    const sevenDayCounts = { urge: 0, porn: 0, relapse: 0, sex: 0, emission: 0 };
    const weekdayCounts = [0, 0, 0, 0, 0, 0, 0]; // 0=Sun, 1=Mon, ...
    let activeDays7 = 0;
    let totalLogItems = 0;
    let totalDaysLogged = 0;
    
    // 统计所有日志
    const sortedDates = Object.keys(logs).sort();
    totalDaysLogged = sortedDates.length;

    sortedDates.forEach((dateStr) => {
        const entries = logs[dateStr];
        if (!Array.isArray(entries)) return;
        
        const entryDate = new Date(dateStr + "T00:00:00");
        const inLast7 = entryDate >= sevenDaysAgo && entryDate <= todayObj;
        if (inLast7 && entries.length > 0) activeDays7++;

        entries.forEach((type) => {
            if (Object.prototype.hasOwnProperty.call(typeCounts, type)) {
                typeCounts[type] += 1;
                if (inLast7) sevenDayCounts[type] += 1;
                totalLogItems += 1;
                
                // 周几分布 (只针对破戒、看片、欲望)
                if (['relapse', 'porn', 'urge', 'emission'].includes(type)) {
                    weekdayCounts[entryDate.getDay()] += 1;
                }
            }
        });
    });

    // 计算最长连胜 (Longest Streak)
    let longestStreak = 0;
    let currentStreakCounter = 0;
    if (startDate) {
        const start = new Date(startDate + "T00:00:00");
        const daysSinceStart = Math.floor((todayObj - start) / (1000 * 60 * 60 * 24));
        currentStreakCounter = Math.max(0, daysSinceStart);
    }
    
    // 简单的最长连胜估算：从记录中寻找破戒间隔
    const breakDates = sortedDates.filter(d => logs[d].includes('relapse') || logs[d].includes('sex'));
    if (breakDates.length === 0) {
        longestStreak = currentStreakCounter;
    } else {
        let maxGap = 0;
        // 初始日期到第一次破戒
        if (startDate) {
            const firstBreak = new Date(breakDates[0] + "T00:00:00");
            const start = new Date(startDate + "T00:00:00"); // 这里简化处理，通常需要一个更早的系统初始日
            maxGap = Math.floor((firstBreak - start) / (1000 * 60 * 60 * 24));
        }
        // 破戒之间的间隔
        for (let i = 0; i < breakDates.length - 1; i++) {
            const d1 = new Date(breakDates[i] + "T00:00:00");
            const d2 = new Date(breakDates[i+1] + "T00:00:00");
            const gap = Math.floor((d2 - d1) / (1000 * 60 * 60 * 24));
            if (gap > maxGap) maxGap = gap;
        }
        // 最后一次破戒到现在
        if (currentStreakCounter > maxGap) maxGap = currentStreakCounter;
        longestStreak = maxGap;
    }

    // 成功率 (Clean Days / Total Days)
    const totalDaysSinceStart = startDate ? Math.floor((todayObj - new Date(startDate + "T00:00:00")) / (1000 * 60 * 60 * 24)) + 1 : 1;
    const cleanDays = Math.max(0, totalDaysSinceStart - typeCounts.relapse - typeCounts.sex);
    const successRate = Math.min(100, Math.round((cleanDays / totalDaysSinceStart) * 100));

    // 精力数据
    const energyValues = Object.values(energy_logs || {}).map((v) => Number(v)).filter((v) => v > 0);
    const avgEnergy = energyValues.length ? (energyValues.reduce((a, b) => a + b, 0) / energyValues.length) : 0;
    const energyCoverage = daysToShow > 0 ? Math.min(100, Math.round((energyValues.length / daysToShow) * 100)) : 0;

    const scoreBand = score >= 80 ? __('insight_stage_high') : score >= 60 ? __('insight_stage_mid') : __('insight_stage_low');
    const riskIndex = Math.min(
        100,
        Math.round(
            sevenDayCounts.relapse * 18 +
            sevenDayCounts.sex * 18 +
            sevenDayCounts.porn * 10 +
            sevenDayCounts.urge * 3 +
            Math.max(0, 8 - avgEnergy) * 4
        )
    );

    // --- 2. 图表数据处理 ---
    // Pie Chart (Relapse Reasons)
    const reasonCounts = relapse_records.reduce((acc, curr) => {
        acc[curr.reason] = (acc[curr.reason] || 0) + 1;
        return acc;
    }, {});
    const totalRelapses = relapse_records.length;
    
    const pieSegments = [];
    let cumulativePercent = 0;
    const colors = ["#ef4444", "#f97316", "#eab308", "#84cc16", "#06b6d4", "#6366f1"];
    let colorIdx = 0;

    Object.entries(reasonCounts).forEach(([reason, count]) => {
        const percent = count / totalRelapses;
        const startX = Math.cos(2 * Math.PI * cumulativePercent);
        const startY = Math.sin(2 * Math.PI * cumulativePercent);
        cumulativePercent += percent;
        const endX = Math.cos(2 * Math.PI * cumulativePercent);
        const endY = Math.sin(2 * Math.PI * cumulativePercent);
        const largeArc = percent > 0.5 ? 1 : 0;
        const pathData = `M 0 0 L ${startX} ${startY} A 1 1 0 ${largeArc} 1 ${endX} ${endY} Z`;
        pieSegments.push({ path: pathData, color: colors[colorIdx % colors.length], reason, count, percent });
        colorIdx++;
    });

    // Line Chart (Energy Trend)
    const dates = [];
    const points = [];
    const getLocalYMD = (d) => {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    for (let i = daysToShow - 1; i >= 0; i--) {
        const d = new Date();
        d.setDate(d.getDate() - i);
        const dateStr = getLocalYMD(d);
        dates.push(dateStr.slice(5)); // MM-DD
        const val = energy_logs[dateStr] || 0;
        points.push(val);
    }

    const todayVal = points[points.length - 1] || 0;
    const coords = points.map((val, idx) => {
        const x = (idx / (daysToShow - 1)) * 100;
        const clampedVal = Math.max(0, Math.min(10, val || 0));
        const y = 85 - (clampedVal / 10) * 70;
        return [x, y];
    });

    let pathD = "";
    if (coords.length > 1) {
        pathD = `M ${coords[0][0]},${coords[0][1]}`;
        for (let i = 0; i < coords.length - 1; i++) {
            const [p0x, p0y] = coords[i];
            const [p1x, p1y] = coords[i + 1];
            const cpx1 = (p0x + p1x) / 2;
            const cpx2 = (p0x + p1x) / 2;
            pathD += ` C ${cpx1},${p0y} ${cpx2},${p1y} ${p1x},${p1y}`;
        }
    }
    const fillPathD = pathD + ` L 100,100 L 0,100 Z`;

    // --- 3. Heatmap Data (Yearly) ---
    const heatmapData = [];
    const heatmapStart = new Date(todayObj);
    heatmapStart.setDate(heatmapStart.getDate() - 180); // 过去半年
    const iterDate = new Date(heatmapStart);
    while (iterDate <= todayObj) {
        const dStr = getLocalYMD(iterDate);
        const entries = logs[dStr] || [];
        let status = 0; // 0: none, 1: urge/emission, 2: porn, 3: relapse/sex
        if (entries.includes('relapse') || entries.includes('sex')) status = 3;
        else if (entries.includes('porn')) status = 2;
        else if (entries.includes('urge') || entries.includes('emission')) status = 1;
        heatmapData.push({ date: dStr, status });
        iterDate.setDate(iterDate.getDate() + 1);
    }

    return (
        <div className="mt-6">
            <button 
                onClick={() => setIsModalOpen(true)}
                className="w-full flex items-center justify-between px-5 py-4 bg-white rounded-2xl border border-gray-100 shadow-md text-gray-700 hover:bg-gray-50 transition-all active:scale-[0.98]"
            >
                <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full bg-teal-50 flex items-center justify-center text-xl shadow-sm border border-teal-100">📊</div>
                    <div className="text-left">
                        <span className="font-bold text-base block">{__('insight_title')}</span>
                        <div className="flex items-center gap-2 mt-0.5">
                            <span className="text-[10px] text-gray-400">{__('insight_days')} {totalDaysSinceStart} {__('insight_days_unit')}</span>
                            <span className="text-[9px] bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded-full font-bold">{__('insight_rate')} {successRate}%</span>
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
                        
                        {/* Modal Header */}
                        <div className="flex items-center justify-between px-6 py-5 bg-white border-b border-gray-100 shrink-0">
                            <div className="flex items-center gap-3">
                                <div className="p-2 bg-teal-50 text-teal-600 rounded-xl">
                                    <window.Activity size={20} />
                                </div>
                                <div>
                                    <h3 className="text-lg font-bold text-gray-800">{__('insight_report')}</h3>
                                    <p className="text-[10px] text-gray-400 uppercase tracking-widest">Deep Insight Analysis</p>
                                </div>
                            </div>
                            <button onClick={() => setIsModalOpen(false)} className="p-2 bg-gray-100 hover:bg-gray-200 rounded-full text-gray-500 transition-colors">
                                <window.CloseIcon size={18} />
                            </button>
                        </div>

                        {/* Modal Body */}
                        <div className="overflow-y-auto p-4 sm:p-8 space-y-6 custom-scrollbar bg-slate-50/50">
                            
                            {/* 第一行：关键指标卡片 */}
                            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                                <div className="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex flex-col items-center justify-center text-center">
                                    <span className="text-[10px] text-gray-400 font-bold uppercase mb-1">{__('insight_streak_current')}</span>
                                    <span className="text-3xl font-bold text-teal-600 font-mono">{currentStreakCounter}</span>
                                    <span className="text-[10px] text-teal-600/60 mt-1">{__('goal_short')}</span>
                                </div>
                                <div className="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex flex-col items-center justify-center text-center">
                                    <span className="text-[10px] text-gray-400 font-bold uppercase mb-1">{__('insight_streak_longest')}</span>
                                    <span className="text-3xl font-bold text-orange-500 font-mono">{longestStreak}</span>
                                    <span className="text-[10px] text-orange-500/60 mt-1">Best Record</span>
                                </div>
                                <div className="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex flex-col items-center justify-center text-center">
                                    <span className="text-[10px] text-gray-400 font-bold uppercase mb-1">{__('insight_success_rate')}</span>
                                    <span className="text-3xl font-bold text-blue-500 font-mono">{successRate}%</span>
                                    <div className="w-full h-1 bg-gray-100 rounded-full mt-2 overflow-hidden">
                                        <div className="h-full bg-blue-500" style={{width: `${successRate}%`}}></div>
                                    </div>
                                </div>
                                <div className="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex flex-col items-center justify-center text-center">
                                    <span className="text-[10px] text-gray-400 font-bold uppercase mb-1">{__('insight_total_logs')}</span>
                                    <span className="text-3xl font-bold text-slate-700 font-mono">{totalLogItems}</span>
                                    <span className="text-[10px] text-slate-700/60 mt-1">Total Logs</span>
                                </div>
                            </div>

                            {/* [New] 全局热力图 - 近180天 */}
                            <div className="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
                                <h4 className="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                                    <span className="w-1.5 h-4 bg-teal-400 rounded-full"></span> {__('insight_heatmap_title')}
                                </h4>
                                <div className="flex flex-wrap gap-1.5 justify-start">
                                    {heatmapData.map((day, i) => {
                                        let bg = 'bg-slate-100';
                                        if (day.status === 1) bg = 'bg-orange-200';
                                        if (day.status === 2) bg = 'bg-orange-400';
                                        if (day.status === 3) bg = 'bg-red-500';
                                        if (day.status === 0 && new Date(day.date) >= new Date(startDate)) bg = 'bg-teal-500';
                                        
                                        return (
                                            <div 
                                                key={i} 
                                                title={day.date}
                                                className={`w-3 h-3 sm:w-4 sm:h-4 rounded-[2px] sm:rounded-[3px] transition-colors ${bg}`}
                                            ></div>
                                        );
                                    })}
                                </div>
                                <div className="mt-4 flex items-center gap-4 text-[10px] text-gray-400">
                                    <div className="flex items-center gap-1"><div className="w-2 h-2 bg-slate-100 rounded-sm"></div> {__('insight_heatmap_none')}</div>
                                    <div className="flex items-center gap-1"><div className="w-2 h-2 bg-teal-500 rounded-sm"></div> {__('insight_heatmap_clean')}</div>
                                    <div className="flex items-center gap-1"><div className="w-2 h-2 bg-orange-200 rounded-sm"></div> {__('urge_btn')}</div>
                                    <div className="flex items-center gap-1"><div className="w-2 h-2 bg-orange-400 rounded-sm"></div> {__('porn_btn')}</div>
                                    <div className="flex items-center gap-1"><div className="w-2 h-2 bg-red-500 rounded-sm"></div> {__('relapse_btn')}/{__('sex_btn')}</div>
                                </div>
                            </div>

                            {/* 第二行：核心数据展示 */}
                            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                
                                {/* 行为分布 */}
                                <div className="lg:col-span-1 bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
                                    <h4 className="text-sm font-bold text-gray-700 mb-6 flex items-center gap-2">
                                        <span className="w-1.5 h-4 bg-teal-500 rounded-full"></span> {__('insight_behavior_dist')}
                                    </h4>
                                    <div className="space-y-4">
                                        {[
                                            { label: `${__('urge_btn')} (Urge)`, count: typeCounts.urge, color: 'bg-orange-500', icon: '🔥' },
                                            { label: `${__('porn_btn')} (Porn)`, count: typeCounts.porn, color: 'bg-yellow-500', icon: '🔞' },
                                            { label: `${__('emission_btn')}`, count: typeCounts.emission, color: 'bg-indigo-500', icon: '💧' },
                                            { label: `${__('sex_btn')} (Sex)`, count: typeCounts.sex, color: 'bg-slate-600', icon: '🛏️' },
                                            { label: `${__('relapse_btn')} (Relapse)`, count: typeCounts.relapse, color: 'bg-red-500', icon: '⚡' }
                                        ].map((item, i) => (
                                            <div key={i}>
                                                <div className="flex justify-between text-xs mb-1.5">
                                                    <span className="text-gray-500 flex items-center gap-1.5">{item.icon} {item.label}</span>
                                                    <span className="font-bold text-gray-800">{item.count}</span>
                                                </div>
                                                <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
                                                    <div className={`h-full ${item.color} rounded-full`} style={{ width: `${totalLogItems ? (item.count / totalLogItems * 100) : 0}%` }}></div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="mt-6 pt-6 border-t border-gray-50 grid grid-cols-2 gap-4">
                                        <div className="text-center">
                                            <div className="text-[10px] text-gray-400 uppercase mb-1">{__('label_7days_active')}</div>
                                            <div className="text-lg font-bold text-gray-700">{activeDays7} {__('insight_days_unit')}</div>
                                        </div>
                                        <div className="text-center">
                                            <div className="text-[10px] text-gray-400 uppercase mb-1">{__('label_avg_energy')}</div>
                                            <div className="text-lg font-bold text-gray-700">{avgEnergy.toFixed(1)}</div>
                                        </div>
                                    </div>
                                </div>

                                {/* 精力趋势图 */}
                                <div className="lg:col-span-2 bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
                                    <div className="flex items-center justify-between mb-6">
                                        <h4 className="text-sm font-bold text-gray-700 flex items-center gap-2">
                                            <span className="w-1.5 h-4 bg-blue-500 rounded-full"></span> {__('insight_energy_trend')} ({daysToShow}{__('insight_days_unit')})
                                        </h4>
                                        <div className="flex items-center gap-4">
                                            <div className="flex items-center gap-1.5">
                                                <div className="w-2 h-2 rounded-full bg-teal-500"></div>
                                                <span className="text-[10px] text-gray-400">{__('insight_today')} {todayVal}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="h-48 w-full relative">
                                        <svg viewBox="0 0 100 100" preserveAspectRatio="none" className="w-full h-full overflow-visible">
                                            <defs>
                                                <linearGradient id="energyGradient" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stopColor="#2dd4bf" stopOpacity="0.3"/>
                                                    <stop offset="100%" stopColor="#2dd4bf" stopOpacity="0"/>
                                                </linearGradient>
                                            </defs>
                                            <line x1="0" y1="50" x2="100" y2="50" stroke="#f1f5f9" strokeWidth="1" strokeDasharray="4 4" />
                                            <path d={fillPathD} fill="url(#energyGradient)" />
                                            <path d={pathD} fill="none" stroke="#0d9488" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" />
                                            {coords.length > 0 && (
                                                <g>
                                                    <circle cx={coords[coords.length-1][0]} cy={coords[coords.length-1][1]} r="3" fill="#fff" stroke="#0d9488" strokeWidth="2" />
                                                </g>
                                            )}
                                        </svg>
                                        <div className="relative h-6 mt-4 w-full">
                                            {dates.map((d, i) => {
                                                const total = dates.length;
                                                const x = (i / (total - 1)) * 100;
                                                let show = (i === 0 || i === total - 1 || i % Math.ceil(total/6) === 0);
                                                if (!show) return null;
                                                return (
                                                    <div key={i} className="absolute top-0 text-[9px] text-gray-400 font-mono transform -translate-x-1/2" style={{ left: `${x}%` }}>{d}</div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* 第三行：深度分析 */}
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                
                                {/* 触发原因分析 */}
                                <div className="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
                                    <h4 className="text-sm font-bold text-gray-700 mb-6 flex items-center gap-2">
                                        <span className="w-1.5 h-4 bg-red-500 rounded-full"></span> {__('insight_trigger_analysis')}
                                    </h4>
                                    {totalRelapses > 0 ? (
                                        <div className="flex flex-col sm:flex-row items-center gap-8">
                                            <div className="w-32 h-32 flex-shrink-0 relative">
                                                <svg viewBox="-1 -1 2 2" className="transform -rotate-90 w-full h-full drop-shadow-lg">
                                                    {pieSegments.map((seg, i) => (
                                                        <path key={i} d={seg.path} fill={seg.color} stroke="white" strokeWidth="0.04" />
                                                    ))}
                                                    <circle cx="0" cy="0" r="0.6" fill="white" />
                                                    <text x="0" y="0" textAnchor="middle" dominantBaseline="middle" className="text-[0.2px] font-bold fill-gray-400">{__('insight_trigger_center')}</text>
                                                </svg>
                                            </div>
                                            <div className="flex-1 w-full space-y-3">
                                                {pieSegments.sort((a,b)=>b.count-a.count).map((seg, i) => (
                                                    <div key={i} className="group">
                                                        <div className="flex items-center justify-between text-xs mb-1">
                                                            <div className="flex items-center gap-2">
                                                                <div className="w-2 h-2 rounded-full" style={{backgroundColor: seg.color}}></div>
                                                                <span className="text-gray-600 font-medium">
                                                                    {(() => {
                                                                        const reasonKey = normalizeReasonKey(seg.reason, __);
                                                                        return reasonKey ? __(reasonKey) : seg.reason;
                                                                    })()}
                                                                </span>
                                                            </div>
                                                            <span className="font-bold text-gray-800">{Math.round(seg.percent*100)}%</span>
                                                        </div>
                                                        <div className="h-1 w-full bg-gray-50 rounded-full overflow-hidden">
                                                            <div className="h-full opacity-30" style={{backgroundColor: seg.color, width: `${seg.percent*100}%`}}></div>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="flex flex-col items-center justify-center py-10 text-center">
                                            <div className="w-16 h-16 bg-teal-50 text-teal-500 rounded-full flex items-center justify-center text-2xl mb-3">🛡️</div>
                                            <p className="text-gray-400 text-xs">{__('insight_no_relapse')}</p>
                                            <p className="text-teal-600 font-bold text-sm mt-1">{__('insight_perfect_defense')}</p>
                                        </div>
                                    )}
                                </div>

                                {/* 周几风险分布 */}
                                <div className="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
                                    <h4 className="text-sm font-bold text-gray-700 mb-6 flex items-center gap-2">
                                        <span className="w-1.5 h-4 bg-orange-500 rounded-full"></span> {__('insight_risk_period')}
                                    </h4>
                                    <div className="h-40 flex items-end justify-between gap-2 px-2">
                                        {[__('weekday_sun'), __('weekday_mon'), __('weekday_tue'), __('weekday_wed'), __('weekday_thu'), __('weekday_fri'), __('weekday_sat')].map((day, i) => {
                                            const count = weekdayCounts[i];
                                            const maxCount = Math.max(...weekdayCounts, 1);
                                            const height = (count / maxCount) * 100;
                                            return (
                                                <div key={i} className="flex-1 flex flex-col items-center gap-2 group">
                                                    <div className="w-full relative flex flex-col items-center justify-end h-full">
                                                        {count > 0 && (
                                                            <div className="absolute -top-6 text-[10px] font-bold text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity">
                                                                {count}
                                                            </div>
                                                        )}
                                                        <div 
                                                            className={`w-full rounded-t-lg transition-all duration-500 ${count === maxCount && count > 0 ? 'bg-orange-500 shadow-lg shadow-orange-100' : 'bg-slate-100 group-hover:bg-slate-200'}`}
                                                            style={{ height: `${height}%`, minHeight: count > 0 ? '4px' : '0' }}
                                                        ></div>
                                                    </div>
                                                    <span className={`text-[10px] font-bold ${count === maxCount && count > 0 ? 'text-orange-600' : 'text-gray-400'}`}>{day}</span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                    <p className="text-[10px] text-gray-400 mt-6 text-center italic">
                                        {Math.max(...weekdayCounts) > 0 
                                            ? __('insight_risk_tip').replace('%s', [__('weekday_sun'), __('weekday_mon'), __('weekday_tue'), __('weekday_wed'), __('weekday_thu'), __('weekday_fri'), __('weekday_sat')][weekdayCounts.indexOf(Math.max(...weekdayCounts))])
                                            : __('insight_no_data')}
                                    </p>
                                </div>

                            </div>

                            {/* 第四行：风险与状态 */}
                            <div className="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
                                <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
                                    <div className="flex-1">
                                        <h4 className="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                                            <span className="w-1.5 h-4 bg-slate-800 rounded-full"></span> {__('insight_risk_index')}
                                        </h4>
                                        <div className="space-y-4">
                                            <div className="flex items-center justify-between text-xs">
                                                <span className="text-gray-500">{__('insight_risk_level')}</span>
                                                <span className={`font-bold ${riskIndex >= 70 ? 'text-red-600' : riskIndex >= 40 ? 'text-yellow-600' : 'text-teal-600'}`}>{riskIndex}/100</span>
                                            </div>
                                            <div className="h-3 rounded-full bg-gray-100 overflow-hidden p-0.5">
                                                <div
                                                    className={`${riskIndex >= 70 ? 'bg-red-500' : riskIndex >= 40 ? 'bg-yellow-500' : 'bg-teal-500'} h-full rounded-full transition-all duration-1000 relative`}
                                                    style={{ width: `${riskIndex}%` }}
                                                >
                                                    <div className="absolute inset-0 bg-white/20 animate-shimmer" style={{ backgroundImage: 'linear-gradient(45deg, rgba(255,255,255,.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.15) 50%, rgba(255,255,255,.15) 75%, transparent 75%, transparent)', backgroundSize: '1rem 1rem' }}></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap gap-3 md:w-72">
                                        <div className="flex-1 min-w-[120px] bg-slate-50 p-3 rounded-xl border border-slate-100">
                                            <div className="text-[10px] text-gray-400 uppercase mb-1">{__('insight_current_stage')}</div>
                                            <div className="text-sm font-bold text-slate-700">{scoreBand}</div>
                                        </div>
                                        <div className="flex-1 min-w-[120px] bg-slate-50 p-3 rounded-xl border border-slate-100">
                                            <div className="text-[10px] text-gray-400 uppercase mb-1">{__('insight_start_date')}</div>
                                            <div className="text-sm font-bold text-slate-700 font-mono">{startDate || __('insight_not_set')}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};
