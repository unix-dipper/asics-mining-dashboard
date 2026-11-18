const state = { language: localStorage.getItem("language") || "en", authenticated: localStorage.getItem("authenticated") === "true", user: null, route: location.hash.slice(1) || "overview", data: {}, mode:"public" };
const $ = (selector) => document.querySelector(selector);
const t = {
  ar: { brand:"منصة التعدين السحابي", overview:"نظرة عامة", live:"التعدين المباشر", transactions:"المعاملات", referrals:"الإحالات", admin:"لوحة الإدارة", security:"الأمان", settings:"الإعدادات", welcome:"مرحباً بك من جديد", dashboard:"لوحة التحكم", active:"نشط", balance:"الرصيد المتاح", hashrate:"قوة التعدين", daily:"الربح اليومي", total:"إجمالي الأرباح", contracts:"عقود التعدين", choose:"اختر خطتك", market:"السوق", miners:"أفضل أجهزة التعدين", deposit:"إيداع", withdraw:"سحب", invite:"دعوة", app:"التطبيق", invest:"استثمر الآن", price:"السعر", totalValue:"الإجمالي", dailyValue:"يومي", login:"تسجيل الدخول", register:"إنشاء حساب", email:"البريد الإلكتروني", password:"كلمة المرور", name:"الاسم الكامل", remember:"تذكرني", demo:"تجربة الحساب التجريبي", save:"حفظ التغييرات", logout:"تسجيل الخروج", noData:"لا توجد بيانات", success:"تم الحفظ بنجاح", loading:"جار التحميل...", notifications:"الإشعارات", securityTitle:"الأمان وكلمة المرور", settingsTitle:"إعدادات الحساب", liveTitle:"مراقبة التعدين المباشر", transactionsTitle:"سجل المعاملات", search:"ابحث عن جهاز...", filter:"تصفية", viewAll:"عرض الكل ›", signInError:"بيانات الدخول غير صحيحة" },
  en: { brand:"Cloud mining platform", overview:"Overview", live:"Live mining", transactions:"Transactions", referrals:"Referrals", admin:"Admin panel", security:"Security", settings:"Settings", welcome:"Welcome back", dashboard:"Dashboard", active:"Active", balance:"Available balance", hashrate:"Mining hashrate", daily:"Daily profit", total:"Total profit", contracts:"Mining contracts", choose:"Choose your plan", market:"Market", miners:"Top mining machines", deposit:"Deposit", withdraw:"Withdraw", invite:"Invite", app:"App", invest:"Invest now", price:"Price", totalValue:"Total", dailyValue:"Daily", login:"Sign in", register:"Create account", email:"Email", password:"Password", name:"Full name", remember:"Remember me", demo:"Try demo account", save:"Save changes", logout:"Log out", noData:"No data yet", success:"Saved successfully", loading:"Loading...", notifications:"Notifications", securityTitle:"Security & password", settingsTitle:"Account settings", liveTitle:"Live mining monitor", transactionsTitle:"Transaction history", search:"Search machines...", filter:"Filter", viewAll:"View all ›", signInError:"Invalid login details" }
};
const tr = (key) => t[state.language][key] || key;
const toast = (message) => { const el = $("#toast"); el.textContent = message; el.classList.add("show"); clearTimeout(window.toastTimer); window.toastTimer = setTimeout(() => el.classList.remove("show"), 2400); };
const esc = (value) => String(value ?? "").replace(/[&<>"']/g, (char) => ({ "&":"&amp;", "<":"&lt;", ">":"&gt;", '"':"&quot;", "'":"&#039;" }[char]));
async function api(path, options = {}) {
  const headers = { "Content-Type":"application/json", ...(options.headers || {}) };
  const response = await fetch(`/api.php${path}`, { ...options, headers });
  const body = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(body.error || "Request failed");
  return body;
}
function setLanguage() { document.documentElement.lang = state.language; document.documentElement.dir = state.language === "ar" ? "rtl" : "ltr"; localStorage.setItem("language", state.language); }
function navItem(route, icon, key) { return `<button class="nav-item ${state.route === route ? "active" : ""}" data-route="${route}"><span>${icon}</span><em>${tr(key)}</em></button>`; }
function shell(content) {
  const avatar = state.user?.avatar ? `<img src="${esc(state.user.avatar)}" alt="Profile" />` : esc(state.user?.initials || "JD");
  return `<div class="app-shell"><header class="topbar"><div class="brand"><div class="brand-mark">₿</div><div><strong>ASICS MINING</strong><span>${tr("brand")}</span></div></div><div class="top-actions"><button class="pill-btn ghost" data-action="language">${state.language === "ar" ? "English" : "العربية"} <span>${state.language === "ar" ? "EN" : "AR"}</span></button><button class="pill-btn vip" data-action="vip">VIP <span>✦</span></button><button class="icon-btn" data-route="notifications" aria-label="Notifications">⌁<i></i></button><div class="avatar">${avatar}</div></div></header><main class="dashboard"><aside class="sidebar panel"><div class="account-card"><div class="account-heading"><div><span class="eyebrow">#${esc(state.user?.accountId || "")}</span><strong>${esc(state.user?.name || "Member")}</strong></div><div class="account-avatar">${state.user?.avatar ? `<img src="${esc(state.user.avatar)}" alt="Profile" />` : "⌁"}</div></div><div class="account-code">KEY ${esc(state.user?.accountId || "")}<span>UID / ${esc(state.user?.uid || "")}</span></div></div><nav class="side-nav">${navItem("overview","⌂","overview")}${navItem("live","◉","live")}${navItem("transactions","▤","transactions")}${navItem("referrals","♧","referrals")}${navItem("security","♙","security")}${navItem("settings","⚙","settings")}</nav><div class="sidebar-footer"><div class="support-card"><span>?</span><div><b>${state.language === "ar" ? "تحتاج مساعدة؟" : "Need help?"}</b><small>Support available 24/7</small></div><strong>›</strong></div><button class="logout" data-action="logout"><span>↪</span><span>${tr("logout")}</span></button></div></aside><section class="content">${content}</section><aside class="right-panel"><div class="search-wrap"><span>⌕</span><input id="searchInput" type="search" placeholder="${tr("search")}" /></div><div class="market-header"><div><span class="eyebrow">${tr("market")}</span><h2>${tr("miners")}</h2></div><button class="filter-btn" data-action="filter">≡</button></div><div class="market-list" id="marketList">${renderMarket(state.data.miners || [])}</div></aside></main></div>`;
}
function renderMarket(miners) { return miners.map((miner) => `<article class="market-card"><div class="market-card-top"><div class="market-name">${esc(miner.name)}<small class="eyebrow">ASIC MINER</small></div><div class="machine">${esc(miner.icon)}</div></div><div class="market-data"><div><small>${tr("price")}</small><b class="income">$${miner.price.toFixed(2)}</b></div><div><small>${tr("totalValue")}</small><b>$${miner.total.toFixed(2)}</b></div><div><small>${tr("dailyValue")}</small><b>$${miner.daily.toFixed(2)}</b></div><button class="buy-btn" data-buy="${miner.id}">${tr("invest")}</button></div></article>`).join("") || `<div class="empty-state">${tr("noData")}</div>`; }
function cardHeading(eyebrow, title) { return `<div class="page-heading"><div><span class="eyebrow">${eyebrow}</span><h1>${title}</h1></div><div class="date-chip">${new Date().toLocaleDateString(state.language === "ar" ? "ar" : "en", { day:"numeric", month:"long", year:"numeric" })}</div></div>`; }
function overview() {
  const d = state.data.dashboard || { balance:400, hashrate:0, dailyProfit:0, totalProfit:0 };
  return `${cardHeading(tr("welcome"), tr("dashboard"))}<div class="stats-grid"><article class="hero-card panel"><div class="card-top"><span class="status live">● ${tr("active")}</span><span class="muted">BTC/USD</span></div><div class="balance-label">${tr("balance")}</div><div class="balance">$${d.balance.toFixed(2)}</div><div class="balance-meta"><span class="up">↗ +8.4%</span><small>Since last month</small></div><div class="account-rig"><div class="account-rig-fan left"><i></i><i></i><i></i><i></i></div><div class="account-rig-fan right"><i></i><i></i><i></i><i></i></div><div class="account-rig-board"><span></span><span></span><span></span><span></span></div><strong>${d.hashrate.toFixed(2)} TH/s</strong></div></article>${metric("◈","purple",tr("hashrate"),`${d.hashrate.toFixed(2)} TH/s`)}${metric("$","amber",tr("daily"),`$${d.dailyProfit.toFixed(2)}`)}${metric("₿","cyan",tr("total"),`$${d.totalProfit.toFixed(2)}`)}</div><div class="quick-actions"><button class="action-card" data-action="deposit"><span class="action-icon green">＋</span>${tr("deposit")}</button><button class="action-card" data-action="withdraw"><span class="action-icon orange">↑</span>${tr("withdraw")}</button><button class="action-card" data-action="invite"><span class="action-icon blue">♧</span>${tr("invite")}</button><button class="action-card" data-action="app"><span class="action-icon purple">▣</span>${tr("app")}</button></div><div class="section-row"><div><span class="eyebrow">${tr("contracts")}</span><h2>${tr("choose")}</h2></div><button class="text-btn" data-route="live">${tr("viewAll")}</button></div><div class="plans">${(state.data.contracts || []).slice(0,3).map(contractCard).join("")}</div>`;
}
function metric(icon, color, label, value) { return `<article class="metric-card panel"><span class="metric-icon ${color}">${icon}</span><div><small>${label}</small><strong>${value}</strong></div></article>`; }
function contractCard(contract) { return `<article class="plan-card"><div class="plan-title"><div class="plan-image">${esc(contract.icon)}</div><div><strong>${esc(contract.name)}</strong><small>SHA-256 · ${contract.hashrate} TH/s</small></div></div><div class="plan-price"><small>${tr("price")}</small><strong>$${contract.price.toFixed(2)}</strong></div><button class="invest-btn" data-buy="${contract.id}">${tr("invest")}</button></article>`; }
function publicPage() {
  const products=state.data.publicProducts || [];
  return `<header class="public-header"><a class="public-brand" href="#" data-public="home"><div class="brand-mark">₿</div><div><strong>ASICS MINING</strong><span>Digital mining infrastructure</span></div></a><nav class="public-nav"><a href="#home" data-public="home">Home</a><a href="#products" data-public="products">Products</a><a href="#about" data-public="about">Who we are</a><a href="#contact" data-public="contact">Contact</a></nav><button class="public-login" data-public-action="login">Sign in</button></header>
  <main id="home" class="public-main">
    <section class="hero-section"><div class="hero-copy"><span class="public-kicker">Cloud infrastructure, professionally managed</span><h1>Mining power.<br><em>Built for the future.</em></h1><p>Access institutional-grade mining hardware through transparent crypto payments and real-time account reporting.</p><div class="hero-actions"><button class="primary-cta" data-public-action="register">Start mining <span>→</span></button><a class="secondary-cta" href="#products" data-public="products">Explore plans</a></div><div class="hero-trust"><span><b>99.9%</b> uptime</span><span><b>8</b> crypto networks</span><span><b>24/7</b> monitoring</span></div></div>
    <div class="rig-stage" aria-label="Animated mining rig"><div class="rig-glow"></div><div class="rig-fan fan-left"><i></i><i></i><i></i><i></i></div><div class="rig-fan fan-right"><i></i><i></i><i></i><i></i></div><div class="rig-core"><div class="rig-board"></div><div class="rig-lights"><i></i><i></i><i></i><i></i><i></i></div></div><div class="rig-data"><span>HASH RATE</span><strong>${products.length ? "127.4" : "127.4"} TH/s</strong><em>● MINING</em></div></div></section>
    <section id="products" class="public-products"><div class="public-section-heading"><div><span>Mining plans</span><h2>Choose your computing power</h2></div><p>Fixed contracts. Transparent yields. No hidden setup fees.</p></div><div class="public-product-grid">${products.map((product)=>`<article class="public-product-card"><div class="public-product-art"><img src="${esc(product.image)}" alt="${esc(product.name)} illustration" /></div><div class="public-product-body"><span class="product-network">SHA-256</span><h3>${esc(product.name)}</h3><div class="product-spec"><div><small>Hashrate</small><strong>${product.hashrate} TH/s</strong></div><div><small>Daily estimate</small><strong>$${Number(product.daily).toFixed(2)}</strong></div></div><div class="public-product-bottom"><div><small>Starting from</small><strong>$${Number(product.price).toFixed(2)}</strong></div><button data-public-action="register">Get started</button></div></div></article>`).join("")}</div></section>
    <section id="about" class="about-section"><div class="about-art"><div class="orbit orbit-one"></div><div class="orbit orbit-two"></div><div class="about-node">ASIC</div></div><div class="about-copy"><span>Who we are</span><h2>Infrastructure you can trust.</h2><p>ASICS Mining connects customers with purpose-built cryptocurrency mining hardware, continuous monitoring, and a clear account experience.</p><div class="about-values"><div><b>01</b><strong>Transparency</strong><small>Clear pricing and visible transaction history.</small></div><div><b>02</b><strong>Security</strong><small>Protected accounts and administrator review.</small></div><div><b>03</b><strong>Support</strong><small>Responsive help across the full customer journey.</small></div></div></div></section>
    <section id="contact" class="contact-section"><div><span>Contact</span><h2>Let’s build your mining plan.</h2><p>Talk to our team about capacity, referrals, or account support.</p></div><form id="contactForm" class="contact-form"><div><label>Name<input name="name" required /></label><label>Email<input name="email" type="email" required /></label></div><label>Message<textarea name="message" rows="5" required></textarea></label><button type="submit">Send message <span>→</span></button></form></section>
  </main><footer class="public-footer"><div class="public-brand"><div class="brand-mark">₿</div><div><strong>ASICS MINING</strong></div></div><p>Digital mining infrastructure.</p><span>© ${new Date().getFullYear()} ASICS Mining</span></footer>`;
}
function settingsPage() {
  const wallets = state.data.wallets || [];
  const avatar = state.user?.avatar ? `<img class="profile-preview" src="${esc(state.user.avatar)}" alt="Profile" />` : `<div class="profile-preview profile-initials">${esc(state.user?.initials || "U")}</div>`;
  return `${cardHeading(tr("settings"), tr("settingsTitle"))}
    <div class="settings-grid">
      <div class="form-panel panel"><div class="profile-header">${avatar}<form id="avatarForm"><label class="upload-label">Change profile picture<input id="avatarInput" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" /></label></form></div>
        <form id="settingsForm"><label>${tr("name")}<input name="name" value="${esc(state.user?.name)}" required /></label><label>${tr("email")}<input name="email" type="email" value="${esc(state.user?.email)}" required /></label><label>Bio<textarea name="bio" rows="3">${esc(state.user?.bio || "")}</textarea></label><label>Language<select name="language"><option value="ar" ${state.language==="ar"?"selected":""}>العربية</option><option value="en" ${state.language==="en"?"selected":""}>English</option></select></label><button class="invest-btn" type="submit">${tr("save")}</button></form>
      </div>
      <div class="form-panel panel"><h2>Withdrawal wallets</h2><p class="muted-copy">Configure one wallet for each supported coin.</p>
        ${(state.data.coins || []).map((coin) => { const saved=wallets.find((wallet)=>wallet.coin===coin.code); return `<form class="wallet-form" data-wallet="${esc(coin.code)}"><label>${esc(coin.name)} (${esc(coin.code)})<input name="address" value="${esc(saved?.address || "")}" placeholder="${esc(coin.symbol)} wallet address" /></label><button type="submit" class="invest-btn">Save</button></form>`; }).join("")}
      </div>
      <div class="form-panel panel"><h2>Request withdrawal</h2><form id="withdrawalForm"><label>Coin<select name="coin">${(state.data.coins || []).map((coin)=>`<option value="${esc(coin.code)}">${esc(coin.name)} (${esc(coin.code)})</option>`).join("")}</select></label><label>Amount in USD<input name="amountUsd" type="number" min="10" step="0.01" value="10" required /></label><label>Destination address <small>(optional when wallet is configured)</small><input name="address" placeholder="Leave blank to use saved wallet" /></label><button class="invest-btn" type="submit">Submit withdrawal</button></form></div>
    </div>`;
}
function referralPage() {
  const referral=state.data.referrals || {code:"",totalEarned:0,users:[]};
  const link=`${location.origin}${location.pathname}?ref=${encodeURIComponent(referral.code)}`;
  const tier=referral.users.length>=10?"10%":referral.users.length>=5?"7%":"5%";
  return `${cardHeading("Referral program","Earn with ASICs Mining")}<div class="stats-grid"><article class="metric-card panel"><span class="metric-icon amber">♧</span><div><small>Referral earnings</small><strong>$${Number(referral.totalEarned).toFixed(2)}</strong></div></article><article class="metric-card panel"><span class="metric-icon purple">♟</span><div><small>Friends joined</small><strong>${referral.users.length}</strong></div></article><article class="metric-card panel"><span class="metric-icon cyan">✦</span><div><small>Your reward tier</small><strong>${tier}</strong></div></article></div><div class="form-panel panel referral-tiers"><h2>Referral tiers</h2><div><span>Starter</span><b>5%</b><small>1-4 active referrals</small></div><div><span>Builder</span><b>7%</b><small>5-9 active referrals</small></div><div><span>Ambassador</span><b>10%</b><small>10+ active referrals</small></div></div><div class="form-panel panel referral-link"><label>Your referral code<input value="${esc(referral.code)}" readonly /></label><label>Your referral link<input id="referralLink" value="${esc(link)}" readonly /></label><button class="invest-btn" id="copyReferral">Copy referral link</button></div><div class="data-panel panel"><h2>Invited users</h2><div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Reward</th><th>Status</th></tr></thead><tbody>${referral.users.map((row)=>`<tr><td>${esc(row.name)}</td><td>${esc(row.email)}</td><td>$${Number(row.amount_usd || 0).toFixed(2)}</td><td>${esc(row.status || "pending")}</td></tr>`).join("")}</tbody></table></div></div>`;
}
function adminPage() {
  const admin=state.data.admin || {};
  const overview=admin.overview || {};
  const users=admin.users || [], payments=admin.payments || [], withdrawals=admin.withdrawals || [], products=admin.products || [];
  return `${cardHeading("Administration","Admin control panel")}<div class="stats-grid"><article class="metric-card panel"><span class="metric-icon purple">♟</span><div><small>Users</small><strong>${overview.users || 0}</strong></div></article><article class="metric-card panel"><span class="metric-icon amber">$</span><div><small>Pending deposits</small><strong>$${Number(overview.pendingPayments||0).toFixed(2)}</strong></div></article><article class="metric-card panel"><span class="metric-icon cyan">↗</span><div><small>Pending withdrawals</small><strong>$${Number(overview.pendingWithdrawals||0).toFixed(2)}</strong></div></article></div>
  <div class="data-panel panel"><h2>Users</h2><div class="table-wrap"><table><thead><tr><th>User</th><th>Email</th><th>Deposited</th><th>Balance</th><th>Owned plans</th><th>Role</th></tr></thead><tbody>${users.map((row)=>`<tr><td>${esc(row.name)}</td><td>${esc(row.email)}</td><td>$${Number(row.deposited).toFixed(2)}</td><td>$${Number(row.balance).toFixed(2)}</td><td>${row.owned}</td><td>${row.isAdmin?"Admin":"User"}</td></tr>`).join("")}</tbody></table></div></div>
  <div class="data-panel panel"><h2>Pending deposits</h2><div class="table-wrap"><table><thead><tr><th>User</th><th>Product</th><th>Coin</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead><tbody>${payments.map((row)=>`<tr><td>${esc(row.name)}</td><td>${esc(row.product_name)}</td><td>${esc(row.network)}</td><td>$${Number(row.amount_usd).toFixed(2)}</td><td>${esc(row.status)}</td><td>${row.status==="pending"?`<button class="mini-action confirm" data-admin-payment="${row.id}" data-action-admin="confirm">Confirm</button><button class="mini-action cancel" data-admin-payment="${row.id}" data-action-admin="cancel">Cancel</button>`:"—"}</td></tr>`).join("")}</tbody></table></div></div>
  <div class="data-panel panel"><h2>Withdrawals</h2><div class="table-wrap"><table><thead><tr><th>User</th><th>Coin</th><th>USD</th><th>Address</th><th>Status</th><th>Actions</th></tr></thead><tbody>${withdrawals.map((row)=>`<tr><td>${esc(row.name)}</td><td>${esc(row.coin)}</td><td>$${Number(row.amount_usd).toFixed(2)}</td><td class="address-cell">${esc(row.address)}</td><td>${esc(row.status)}</td><td>${row.status==="pending"?`<button class="mini-action confirm" data-admin-withdrawal="${row.id}" data-action-admin="approve">Approve</button><button class="mini-action cancel" data-admin-withdrawal="${row.id}" data-action-admin="reject">Reject</button>`:"—"}</td></tr>`).join("")}</tbody></table></div></div>
  <div class="data-panel panel"><h2>Available plans</h2><div class="table-wrap"><table><thead><tr><th>Name</th><th>Price</th><th>Daily</th><th>Hashrate</th><th>Status</th></tr></thead><tbody>${products.map((row)=>`<tr><td>${esc(row.name)}</td><td>$${Number(row.price_usd).toFixed(2)}</td><td>$${Number(row.daily_profit_usd).toFixed(2)}</td><td>${Number(row.hashrate).toFixed(2)} TH/s</td><td>${row.active?"Active":"Inactive"}</td></tr>`).join("")}</tbody></table></div></div>`;
}
function pageContent() {
  if (state.route === "live") return `${cardHeading(tr("contracts"), tr("liveTitle"))}<div class="live-rig-panel panel"><div class="live-rig-status"><span class="status live">● LIVE</span><b>${(state.data.dashboard?.hashrate || 0).toFixed(2)} TH/s</b></div><div class="live-rig"><div class="live-rig-fan fan-a"></div><div class="live-rig-fan fan-b"></div><div class="live-rig-core"><i></i><i></i><i></i><i></i><i></i><i></i></div></div><div class="live-rig-stats"><span>Uptime<b>99.9%</b></span><span>Temperature<b>58°C</b></span><span>Active rigs<b>${(state.data.contracts || []).length}</b></span></div></div><div class="page-grid">${(state.data.contracts || []).map(contractCard).join("")}</div><div class="data-panel panel"><h2>${tr("hashrate")}</h2><div class="progress-list">${(state.data.contracts || []).map((item) => `<div class="progress-row"><span>${esc(item.name)}</span><b>${item.hashrate} TH/s</b><div><i style="width:${Math.min(item.hashrate / 2, 100)}%"></i></div></div>`).join("")}</div></div>`;
  if (state.route === "transactions") return `${cardHeading(tr("transactions"), tr("transactionsTitle"))}<div class="data-panel panel"><div class="table-wrap"><table><thead><tr><th>Reference</th><th>Date</th><th>Type</th><th>Coin</th><th>Amount</th><th>Status</th></tr></thead><tbody>${(state.data.transactions || []).map((item) => `<tr><td>${esc(item.reference)}</td><td>${esc(item.date)}</td><td>${esc(item.type)}</td><td>${esc(item.coin || "USD")}</td><td>$${Math.abs(item.amount).toFixed(2)}</td><td><span class="status ${item.status === "completed" || item.status === "paid" ? "live" : item.status === "cancelled" ? "cancelled" : "pending"}">${esc(item.status)}</span></td></tr>`).join("")}</tbody></table></div></div>`;
  if (state.route === "security") return `${cardHeading(tr("security"), tr("securityTitle"))}<div class="form-panel panel"><form id="passwordForm"><label>${tr("password")}<input name="currentPassword" type="password" required /></label><label>New password<input name="newPassword" type="password" minlength="6" required /></label><label>Confirm password<input name="confirmPassword" type="password" minlength="6" required /></label><button class="invest-btn" type="submit">${tr("save")}</button></form></div>`;
  if (state.route === "settings") return settingsPage();
  if (state.route === "referrals") return referralPage();
  if (state.route === "admin" && state.user?.isAdmin) return adminPage();
  if (state.route === "notifications") return `${cardHeading(tr("notifications"), tr("notifications"))}<div class="data-panel panel notification-list">${(state.data.notifications || []).map((item) => `<div class="notification"><span>⌁</span><div><b>${esc(item.title)}</b><small>${esc(item.message)}</small></div><time>${esc(item.date)}</time></div>`).join("")}</div>`;
  return overview();
}
function render() {
  setLanguage();
  if (!state.authenticated && state.mode === "public") { $("#app").innerHTML = publicPage(); bindPublicEvents(); return; }
  if (!state.authenticated) { $("#app").innerHTML = loginPage(); bindLoginEvents(); return; }
  $("#app").innerHTML = shell(pageContent()); bindEvents();
}
function loginPage() { return `<div class="auth-page"><button class="auth-back" data-public-action="public">← Back to website</button><div class="auth-card panel"><div class="brand auth-brand"><div class="brand-mark">₿</div><div><strong>ASICS MINING</strong><span>${tr("brand")}</span></div></div><h1 id="authTitle">${tr("login")}</h1><p class="auth-subtitle">Access your mining account</p><form id="authForm"><div id="nameField" class="hidden"><label>${tr("name")}<input name="name" autocomplete="name" /></label><label>Referral code<input name="referralCode" placeholder="ASIC-..." /></label></div><label>${tr("email")}<input name="email" type="email" autocomplete="email" required /></label><label>${tr("password")}<input name="password" type="password" autocomplete="current-password" required /></label><button class="invest-btn auth-submit" type="submit">${tr("login")}</button></form><button class="text-btn auth-switch" data-action="switchAuth">${tr("register")}</button><button class="language-link" data-action="language">${state.language === "ar" ? "English" : "العربية"}</button></div></div>`; }
function bindPublicEvents() {
  document.querySelectorAll("[data-public-action]").forEach((element)=>element.addEventListener("click",(event)=>{
    event.preventDefault();
    const action=element.dataset.publicAction;
    if(action==="login"){state.mode="auth";window.registerMode=false;render();}
    if(action==="register"){state.mode="auth";window.registerMode=true;render();setTimeout(()=>$("#nameField")?.classList.remove("hidden"),0);}
    if(action==="public"){state.mode="public";render();}
  }));
  document.querySelectorAll("[data-public]").forEach((element)=>element.addEventListener("click",(event)=>{
    const section=element.dataset.public;
    const target=document.querySelector(`#${section}`);
    if(target){event.preventDefault();target.scrollIntoView({behavior:"smooth"});}
  }));
  $("#contactForm")?.addEventListener("submit",async(event)=>{
    event.preventDefault();
    const data=Object.fromEntries(new FormData(event.currentTarget));
    try{await api("/contact",{method:"POST",body:JSON.stringify(data)});toast("Message sent");event.currentTarget.reset();}catch(error){toast(error.message);}
  });
}
function bindLoginEvents() {
  const referral=new URLSearchParams(location.search).get("ref");
  if(referral && $("#authForm input[name=referralCode]")) $("#authForm input[name=referralCode]").value=referral;
  document.querySelectorAll("[data-public-action]").forEach((element)=>{
    element.addEventListener("click",(event)=>{
      event.preventDefault();
      if(element.dataset.publicAction==="public"){
        state.mode="public";
        location.hash="";
        render();
      }
    });
  });
  document.querySelectorAll(".auth-card [data-action]").forEach((element) => {
    element.addEventListener("click", () => handleAction(element.dataset.action));
  });
  $("#authForm")?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const submitButton = event.currentTarget.querySelector(".auth-submit");
    submitButton.disabled = true;
    submitButton.textContent = tr("loading");
    const form = Object.fromEntries(new FormData(event.currentTarget));
    try {
      await api(window.registerMode ? "/auth/register" : "/auth/login", {
        method:"POST",
        body:JSON.stringify(form)
      });
      state.authenticated = true;
      localStorage.setItem("authenticated", "true");
      await loadData();
    } catch (error) {
      submitButton.disabled = false;
      submitButton.textContent = window.registerMode ? tr("register") : tr("login");
      toast(error.message || tr("signInError"));
    }
  });
}
function bindEvents() {
  document.querySelectorAll("[data-route]").forEach((el) => el.addEventListener("click", () => { state.route = el.dataset.route; location.hash = state.route; loadPage(); }));
  document.querySelectorAll("[data-action]").forEach((el) => el.addEventListener("click", () => handleAction(el.dataset.action)));
  document.querySelectorAll("[data-buy]").forEach((el) => el.addEventListener("click", () => purchase(el.dataset.buy)));
  $("#searchInput")?.addEventListener("input", (event) => { const query = event.target.value.toLowerCase(); $("#marketList").innerHTML = renderMarket((state.data.miners || []).filter((item) => item.name.toLowerCase().includes(query))); });
  $("#passwordForm")?.addEventListener("submit", updatePassword); $("#settingsForm")?.addEventListener("submit", updateSettings);
  $("#avatarInput")?.addEventListener("change", uploadAvatar);
  document.querySelectorAll(".wallet-form").forEach((form)=>form.addEventListener("submit",saveWallet));
  $("#withdrawalForm")?.addEventListener("submit",requestWithdrawal);
  $("#copyReferral")?.addEventListener("click",copyReferral);
  document.querySelectorAll("[data-action-admin]").forEach((button)=>button.addEventListener("click",adminAction));
}
async function handleAction(action) {
  if (action === "language") { state.language = state.language === "ar" ? "en" : "ar"; render(); return; }
  if (action === "vip") { showVipModal(); return; }
  if (action === "logout") { try { await api("/auth/logout", { method:"POST", body:"{}" }); } catch (_) {} localStorage.removeItem("authenticated"); state.authenticated = false; state.user = null; state.data = {}; state.mode="public"; location.hash=""; render(); return; }
  if (action === "switchAuth") { window.registerMode = !window.registerMode; const title = $("#authTitle"); if (title) title.textContent = window.registerMode ? tr("register") : tr("login"); $("#nameField")?.classList.toggle("hidden", !window.registerMode); document.querySelector(".auth-submit").textContent = window.registerMode ? tr("register") : tr("login"); document.querySelector(".auth-switch").textContent = window.registerMode ? tr("login") : tr("register"); return; }
  if (action === "filter") { state.data.miners.reverse(); $("#marketList").innerHTML = renderMarket(state.data.miners); bindEvents(); return; }
  if (action === "deposit") { state.route="live"; location.hash="live"; await loadPage(); return; }
  if (action === "withdraw") { state.route="settings"; location.hash="settings"; await loadPage(); return; }
  if (action === "invite") { state.route="referrals"; location.hash="referrals"; await loadPage(); return; }
  if (action === "app") toast(state.language === "ar" ? "تطبيق الهاتف جاهز للتثبيت من المتصفح" : "Install this app from your browser menu");
}
function showVipModal(){
  const vip=state.data.vip || {active:false,claimedToday:false,monthlyActivations:0,monthlyReward:5,rewardClaimed:false};
  const modal=document.createElement("div");
  modal.className="modal-backdrop";
  modal.innerHTML=`<div class="invoice-modal panel vip-modal"><button class="modal-close">×</button><span class="eyebrow">VIP Mining</span><h2>${vip.active?"Your VIP membership":"Unlock VIP Mining"}</h2><div class="vip-benefits"><div><strong>$9.99</strong><span>30-day VIP access</span></div><div><strong>24H</strong><span>Free machine activation</span></div><div><strong>$5</strong><span>After 30 daily activations</span></div></div>${vip.active?`<div class="vip-status-line"><span>Subscription until</span><b>${esc(vip.until||"Active")}</b></div><div class="vip-progress"><span>Monthly activations</span><strong>${vip.monthlyActivations}/30</strong><div><i style="width:${Math.min(100,vip.monthlyActivations/30*100)}%"></i></div></div><button class="invest-btn" data-vip-activate ${vip.claimedToday?"disabled":""}>${vip.claimedToday?"Activated today":"Activate 24H free machine"}</button>`:`<button class="invest-btn" data-vip-subscribe>Subscribe with crypto</button>`}</div>`;
  document.body.appendChild(modal);
  modal.addEventListener("click",async(event)=>{
    if(event.target===modal||event.target.closest(".modal-close")) return modal.remove();
    if(event.target.closest("[data-vip-subscribe]")){
      const result=await api("/vip/subscribe",{method:"POST",body:JSON.stringify({network:"USDT-TRC20"})}).catch(error=>({error:error.message}));
      if(result.error) return toast(result.error);
      modal.remove();showInvoice({...result,product:{name:"VIP 30-Day Subscription"}});
    }
    if(event.target.closest("[data-vip-activate]")){
      try{
        const result=await api("/vip/activate",{method:"POST",body:"{}"});
        modal.remove();toast(result.monthlyPayout?"$5 VIP reward added":"24-hour mining machine activated");await loadData();
      }catch(error){toast(error.message);}
    }
  });
}
async function purchase(id) {
  const coins = state.data.coins || [];
  if (!coins.length) coins.push({code:"BTC",name:"Bitcoin",symbol:"BTC"});
  const modal=document.createElement("div");
  modal.className="modal-backdrop";
  modal.innerHTML=`<div class="invoice-modal panel"><button class="modal-close">×</button><span class="eyebrow">Payment method</span><h2>Choose cryptocurrency</h2><div class="coin-options">${coins.map((coin)=>`<button class="coin-option" data-network="${esc(coin.code)}"><strong>${esc(coin.symbol)}</strong><span>${esc(coin.name)}<small>1 ${esc(coin.symbol)} ≈ $${Number(coin.rate).toLocaleString()}</small></span></button>`).join("")}</div></div>`;
  document.body.appendChild(modal);
  modal.addEventListener("click",async (event)=>{
    if(event.target===modal||event.target.closest(".modal-close")) return modal.remove();
    const option=event.target.closest("[data-network]");
    if(!option) return;
    modal.remove();
    try { const invoice=await api("/payments/invoice",{method:"POST",body:JSON.stringify({productId:Number(id),network:option.dataset.network})}); showInvoice(invoice); } catch(error){toast(error.message);}
  });
}
function showInvoice(invoice) {
  const modal = document.createElement("div");
  modal.className = "modal-backdrop";
  modal.innerHTML = `<div class="invoice-modal panel"><button class="modal-close" aria-label="Close">×</button><span class="eyebrow">${state.language === "ar" ? "فاتورة دفع بالعملات الرقمية" : "Crypto payment invoice"}</span><h2>${esc(invoice.product.name)}</h2><div class="invoice-amount"><strong>${invoice.amountCrypto} ${invoice.network}</strong><small>$${Number(invoice.amountUsd).toFixed(2)}</small></div><p>${state.language === "ar" ? "أرسل المبلغ إلى العنوان التالي. سيتم تفعيل المنتج بعد تأكيد الدفع من مزود الدفع." : "Send the amount to the address below. The product activates after payment confirmation from the payment provider."}</p><div class="address-box">${esc(invoice.address)}<button data-copy="${esc(invoice.address)}">Copy</button></div><div class="invoice-meta"><span>${esc(invoice.invoiceCode)}</span><span class="status pending">${invoice.status}</span></div><button class="invest-btn" data-refresh-invoice="${invoice.id}">${state.language === "ar" ? "تحقق من حالة الدفع" : "Check payment status"}</button><small class="invoice-note">${state.language === "ar" ? "للدفع الحقيقي، اضبط CRYPTO_PAYMENT_ADDRESS ووصّل webhook مزود الدفع." : "For live payments, configure CRYPTO_PAYMENT_ADDRESS and connect your provider webhook."}</small></div>`;
  document.body.appendChild(modal);
  modal.addEventListener("click", async (event) => {
    if (event.target === modal || event.target.closest(".modal-close")) return modal.remove();
    const copy = event.target.closest("[data-copy]"); if (copy) { await navigator.clipboard?.writeText(copy.dataset.copy); toast(state.language === "ar" ? "تم نسخ العنوان" : "Address copied"); }
    const refresh = event.target.closest("[data-refresh-invoice]");
    if (refresh) {
      modal.remove();
      state.route="transactions";
      location.hash="transactions";
      try {
        await api(`/payments/invoice/${refresh.dataset.refreshInvoice}`);
      } catch (_) {}
      await loadData();
    }
  });
}
async function updatePassword(event) { event.preventDefault(); const data = Object.fromEntries(new FormData(event.target)); if (data.newPassword !== data.confirmPassword) return toast("Passwords do not match"); try { await api("/me/password", { method:"PATCH", body:JSON.stringify(data) }); toast(tr("success")); event.target.reset(); } catch (error) { toast(error.message); } }
async function updateSettings(event) { event.preventDefault(); const data = Object.fromEntries(new FormData(event.target)); try { const result = await api("/me", { method:"PATCH", body:JSON.stringify(data) }); state.user = result.user; if (data.language !== state.language) state.language = data.language; toast(tr("success")); render(); } catch (error) { toast(error.message); } }
async function uploadAvatar(event) {
  const file=event.target.files?.[0]; if(!file) return;
  const form=new FormData(); form.append("avatar",file);
  try {
    const response=await fetch("/api.php/me/avatar",{method:"POST",body:form,credentials:"same-origin"});
    const result=await response.json().catch(()=>({}));
    if(!response.ok) throw new Error(result.error||"Avatar upload failed");
    state.user=result.user; toast("Profile picture updated"); render();
  } catch(error){toast(error.message);}
}
async function saveWallet(event) {
  event.preventDefault();
  const coin=event.currentTarget.dataset.wallet;
  const address=new FormData(event.currentTarget).get("address");
  try { await api("/wallets",{method:"POST",body:JSON.stringify({coin,address})}); toast("Wallet saved"); await loadData(); } catch(error){toast(error.message);}
}
async function requestWithdrawal(event) {
  event.preventDefault();
  const data=Object.fromEntries(new FormData(event.currentTarget));
  try { const result=await api("/withdrawals",{method:"POST",body:JSON.stringify({coin:data.coin,amountUsd:Number(data.amountUsd),address:data.address})}); toast("Withdrawal submitted"); await loadData(); } catch(error){toast(error.message);}
}
async function copyReferral() {
  const input=$("#referralLink");
  await navigator.clipboard?.writeText(input.value);
  toast("Referral link copied");
}
async function adminAction(event) {
  const button=event.currentTarget;
  const action=button.dataset.actionAdmin;
  if(button.dataset.adminPayment) {
    const txid=action==="confirm"?prompt("Enter blockchain transaction ID (optional):",""):"";
    try { await api(`/admin/payments/${button.dataset.adminPayment}/${action}`,{method:"POST",body:JSON.stringify({txid:txid||""})}); toast(action==="confirm"?"Payment confirmed":"Payment cancelled"); await loadData(); } catch(error){toast(error.message);}
  }
  if(button.dataset.adminWithdrawal) {
    const txid=action==="approve"?prompt("Enter payout transaction ID (optional):",""):"";
    const note=action==="reject"?prompt("Cancellation reason:","Rejected by administrator"):"";
    try { await api(`/admin/withdrawals/${button.dataset.adminWithdrawal}/${action}`,{method:"POST",body:JSON.stringify({txid:txid||"",note:note||""})}); toast(action==="approve"?"Withdrawal approved":"Withdrawal rejected"); await loadData(); } catch(error){toast(error.message);}
  }
}
async function loadData() {
  const [me,dashboard,miners,contracts,transactions,notifications,wallets,coins,referrals,vip] = await Promise.all([api("/me"),api("/dashboard"),api("/miners"),api("/contracts"),api("/transactions"),api("/notifications"),api("/wallets"),api("/coins"),api("/referrals"),api("/vip/status")]);
  state.user=me.user;
  state.data={dashboard,miners,contracts,transactions,notifications,wallets,coins,referrals,vip};
  if(state.user.isAdmin) {
    const [overview,users,payments,withdrawals,products] = await Promise.all([api("/admin/overview"),api("/admin/users"),api("/admin/payments"),api("/admin/withdrawals"),api("/admin/products")]);
    state.data.admin={overview,users,payments,withdrawals,products};
  }
  render();
}
async function loadPage() { try { await loadData(); } catch (error) { localStorage.removeItem("authenticated"); state.authenticated = false; render(); toast(error.message); } }
window.addEventListener("hashchange", () => { state.route = location.hash.slice(1) || "overview"; if (state.authenticated) loadPage(); });
async function loadPublicData() {
  try {
    const [products,coins]=await Promise.all([api("/public/products"),api("/coins")]);
    state.data.publicProducts=products;
    state.data.coins=coins;
  } catch (_) {}
  render();
}
if (state.authenticated) {
  loadPage();
} else {
  loadPublicData();
}
