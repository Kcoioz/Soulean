# Soulean 清心 / Soulean — Self-Discipline Tracker

自律追踪与自我修养 Web 应用。记录欲望、破戒事件，追踪精力状态，通过连续天数、分数系统、深度洞察和里程碑庆祝帮助你重塑习惯。

A self-discipline tracking web app. Log urges and relapses, track energy levels, and reshape habits through streak counters, scoring, deep insights, and milestone celebrations.

## 技术栈 / Tech Stack

| 层级 Layer | 技术 Tech |
|-----------|-----------|
| 前端 Frontend | React 18 (JSX via Babel standalone), Tailwind CSS |
| 后端 Backend | PHP 7.4+ |
| 存储 Storage | JSON file / MySQL / SQLite |
| 国际化 i18n | 简体中文 / 繁體中文 / English |

## 快速开始 / Quick Start

1. 上传所有文件到 Web 服务器 / Upload all files to web server
2. 确保项目根目录可写 / Ensure project root is writable
3. 访问 `install.php` 完成安装 / Visit `install.php` to install
4. 安装后自动跳转登录页 / Auto-redirects to login after install


### 服务器配置 / Server Config

**Apache**: 自动生成 `.htaccess` 保护敏感文件。
**Nginx**: 请在 `nginx.conf` 或站点配置中添加以下规则保护数据文件：

```nginx
# 拒绝直接访问数据文件
location ~ \.(json|db|sqlite|sqlite3)$ {
    deny all;
    return 403;
}

# 拒绝访问配置文件
location ~ /(config|db_config)\.php$ {
    deny all;
    return 403;
}

# 禁止目录浏览
autoindex off;
```

## 核心功能 / Features

- **事件记录 Event Logging** — 欲望/看片/破戒/遗精/床事，不同行为扣分不同
- **精力追踪 Energy Tracking** — 每日精力打分 (1-10)，趋势可视化
- **连续天数 Dual Streaks** — 清心天数 + 戒色天数双重统计
- **撤消记录 Undo** — 删除记录自动补回分数，修正误操作
- **日历热力图 Calendar Heatmap** — 年度记录概览，颜色标记严重程度
- **深度洞察报告 Deep Insight** — 行为分布、精力趋势、触发原因分析、风险指数
- **里程碑庆祝 Milestones** — 7/14/21/28/50/100/200/300/365 天自动弹窗
- **记录浏览器 Record Browser** — 年度日历检索 + 全文搜索
- **每日随笔 Daily Notes** — 每日感悟记录与历史查看
- **SOS 呼吸急救 Panic Button** — 4-7-8 呼吸法引导
- **邮件报告 Email Reports** — 自动/手动发送修身报告
- **多设备登录 Multi-Device** — 支持多设备同时登录，互不干扰
- **会话管理 Session Mgmt** — 后台查看活跃会话，IP/浏览器/设备详情，一键踢出
- **暗色主题 Dark Mode** — Light / Dark 切换
- **三语言 Trilingual** — 简体中文 / 繁體中文 / English (登录页也可切换)

## 项目结构 / Project Structure

```
├── index.php              # 前端 SPA 入口 / Frontend entry
├── api.php                # RESTful API (?action= 路由)
├── config.php             # 核心配置、数据抽象层、会话管理 / Config & data layer
├── admin.php              # 后台管理面板 / Admin panel
├── login.php              # 登录/密码重置 / Login & password reset
├── install.php            # 安装向导 (安装后自动删除) / Installer
├── cron.php               # 定时任务 / Cron jobs
├── mail.php               # SMTP 邮件模块 / Mail module
├── data.json              # JSON 模式数据文件 / JSON mode data
├── js/
│   ├── components.js      # 核心 UI 组件 / Core components (~780 lines)
│   ├── analysis.js        # 深度洞察报告 / Analysis dashboard (~520 lines)
│   ├── record-browser.js  # 记录浏览器 / Record browser (~270 lines)
│   ├── hooks.js           # 数据钩子 / Data hooks (~216 lines)
│   ├── app.js             # App 主组件 & 渲染 / App & render (~550 lines)
│   ├── react.production.min.js
│   ├── react-dom.production.min.js
│   └── tailwind.js        # Tailwind CSS (Play CDN)
├── css/
│   ├── style.css
│   └── admin_style.css
├── dictionary/
│   ├── frontend.php       # 前端语言包 / Frontend translations
│   └── admin.php          # 后台语言包 / Admin translations
└── favicon.ico
```

**JS 加载顺序 / Load order**: `components.js → analysis.js → record-browser.js → hooks.js → app.js`

## 安全 / Security

- CSRF Token 防护 / CSRF protection
- Session Cookie HttpOnly + SameSite=Lax
- 24小时无活动自动登出 / 24h inactivity auto-logout
- 密码 bcrypt 哈希 / Password bcrypt hashing
- PDO 预处理语句 / Prepared statements
- XSS 防护头 / XSS protection headers
- 安装脚本用后自动删除 / Installer self-deletes
- 会话追踪与踢出 / Session tracking & remote logout
- 登录尝试速率限制 / Login rate limiting (15min lockout)
- 敏感文件访问保护 / Sensitive file access protection

## License

MIT
