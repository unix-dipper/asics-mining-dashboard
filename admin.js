const adminToast=(message)=>{const el=document.querySelector("#toast");el.textContent=message;el.classList.add("show");setTimeout(()=>el.classList.remove("show"),2400)};
const adminEsc=(value)=>String(value??"").replace(/[&<>"']/g,(char)=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[char]));
async function adminApi(path,options={}){const response=await fetch("/api.php"+path,{...options,headers:{"Content-Type":"application/json",...(options.headers||{})}});const body=await response.json().catch(()=>({}));if(!response.ok)throw new Error(body.error||"Request failed");return body;}
function adminShell(data){
  return `<aside class="admin-sidebar"><div class="admin-logo"><div class="brand-mark">₿</div><div><strong>ASICs Mining</strong><span>Administration</span></div></div><nav><button class="active" data-admin-section="overview">Overview</button><button data-admin-section="users">Users</button><button data-admin-section="payments">Deposits</button><button data-admin-section="withdrawals">Withdrawals</button><button data-admin-section="products">Plans</button><button data-admin-section="messages">Contact</button></nav><a href="/" class="admin-back">← Website</a></aside><main class="admin-main"><header class="admin-header"><div><span>Administration</span><h1 id="adminTitle">Overview</h1></div><div class="admin-profile"><div class="avatar">AD</div><div><b>${adminEsc(data.user.name)}</b><small>Administrator</small></div></div></header><div id="adminContent">${adminOverview(data)}</div></main>`;
}
function adminOverview(data){const o=data.overview;return `<div class="admin-stat-grid"><article><span>Total users</span><strong>${o.users}</strong></article><article><span>Paid deposits</span><strong>$${Number(o.paidPayments).toFixed(2)}</strong></article><article><span>Pending deposits</span><strong>$${Number(o.pendingPayments).toFixed(2)}</strong></article><article><span>Pending withdrawals</span><strong>$${Number(o.pendingWithdrawals).toFixed(2)}</strong></article></div><div class="admin-panels"><section><h2>Recent users</h2>${adminUsersTable(data.users.slice(0,6))}</section><section><h2>Recent deposits</h2>${adminPaymentsTable(data.payments.slice(0,8))}</section></div>`}
function adminUsersTable(rows){return `<div class="admin-table-wrap"><table><thead><tr><th>User</th><th>Email</th><th>Deposited</th><th>Balance</th><th>Plans</th><th>Role</th></tr></thead><tbody>${rows.map(r=>`<tr><td>${adminEsc(r.name)}</td><td>${adminEsc(r.email)}</td><td>$${Number(r.deposited).toFixed(2)}</td><td>$${Number(r.balance).toFixed(2)}</td><td>${r.owned}</td><td>${r.isAdmin?"Admin":"User"}</td></tr>`).join("")}</tbody></table></div>`}
function adminPaymentsTable(rows){return `<div class="admin-table-wrap"><table><thead><tr><th>User</th><th>Plan</th><th>Network</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody>${rows.map(r=>`<tr><td>${adminEsc(r.name)}<small>${adminEsc(r.email)}</small></td><td>${adminEsc(r.product_name)}</td><td>${adminEsc(r.network)}</td><td>$${Number(r.amount_usd).toFixed(2)}</td><td><span class="admin-status ${adminEsc(r.status)}">${adminEsc(r.status)}</span></td><td>${r.status==="pending"?`<button class="admin-confirm" data-payment="${r.id}" data-action="confirm">Confirm</button><button class="admin-cancel" data-payment="${r.id}" data-action="cancel">Cancel</button>`:"—"}</td></tr>`).join("")}</tbody></table></div>`}
function adminWithdrawalsTable(rows){return `<div class="admin-table-wrap"><table><thead><tr><th>User</th><th>Coin</th><th>Amount</th><th>Destination</th><th>Status</th><th>Action</th></tr></thead><tbody>${rows.map(r=>`<tr><td>${adminEsc(r.name)}<small>${adminEsc(r.email)}</small></td><td>${adminEsc(r.coin)}</td><td>$${Number(r.amount_usd).toFixed(2)}</td><td class="admin-address">${adminEsc(r.address)}</td><td><span class="admin-status ${adminEsc(r.status)}">${adminEsc(r.status)}</span></td><td>${r.status==="pending"?`<button class="admin-confirm" data-withdrawal="${r.id}" data-action="approve">Approve</button><button class="admin-cancel" data-withdrawal="${r.id}" data-action="reject">Reject</button>`:"—"}</td></tr>`).join("")}</tbody></table></div>`}
async function loadAdmin(){
  try{
    const me=await adminApi("/me");if(!me.user.isAdmin){location.replace("/");return;}
    const [overview,users,payments,withdrawals,products,messages]=await Promise.all([adminApi("/admin/overview"),adminApi("/admin/users"),adminApi("/admin/payments"),adminApi("/admin/withdrawals"),adminApi("/admin/products"),adminApi("/admin/messages")]);
    const data={user:me.user,overview,users,payments,withdrawals,products,messages};
    document.querySelector("#adminApp").innerHTML=adminShell(data);
    bindAdmin(data);
  }catch(error){location.replace("/");}
}
function bindAdmin(data){
  document.querySelectorAll("[data-admin-section]").forEach(button=>button.addEventListener("click",()=>{
    document.querySelectorAll("[data-admin-section]").forEach(item=>item.classList.remove("active"));button.classList.add("active");
    const section=button.dataset.adminSection;
    const content=document.querySelector("#adminContent"),title=document.querySelector("#adminTitle");
    const names={overview:"Overview",users:"Users",payments:"Deposits",withdrawals:"Withdrawals",products:"Mining plans",messages:"Contact"};
    title.textContent=names[section];
    content.innerHTML=section==="overview"?adminOverview(data):section==="users"?`<section class="admin-panel-full"><h2>All users</h2>${adminUsersTable(data.users)}</section>`:section==="payments"?`<section class="admin-panel-full"><h2>Deposit requests</h2>${adminPaymentsTable(data.payments)}</section>`:section==="withdrawals"?`<section class="admin-panel-full"><h2>Withdrawal requests</h2>${adminWithdrawalsTable(data.withdrawals)}</section>`:section==="messages"?`<section class="admin-panel-full"><h2>Contact messages</h2><div class="admin-table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Message</th><th>Date</th></tr></thead><tbody>${data.messages.map(m=>`<tr><td>${adminEsc(m.name)}</td><td>${adminEsc(m.email)}</td><td>${adminEsc(m.message)}</td><td>${adminEsc(m.created_at)}</td></tr>`).join("")}</tbody></table></div></section>`:`<section class="admin-panel-full"><h2>${names[section]}</h2><div class="admin-table-wrap"><table><thead><tr><th>Name</th><th>Price</th><th>Daily</th><th>Hashrate</th><th>Status</th></tr></thead><tbody>${data.products.map(p=>`<tr><td>${adminEsc(p.name)}</td><td>$${Number(p.price_usd).toFixed(2)}</td><td>$${Number(p.daily_profit_usd).toFixed(2)}</td><td>${Number(p.hashrate).toFixed(2)} TH/s</td><td>${p.active?"Active":"Inactive"}</td></tr>`).join("")}</tbody></table></div></section>`;
    bindActions();
  }));
  bindActions();
}
function bindActions(){
  document.querySelectorAll("[data-payment]").forEach(button=>button.addEventListener("click",async()=>{
    const action=button.dataset.action;
    const txid=action==="confirm"?prompt("Blockchain transaction ID (optional):",""):"";
    try{await adminApi(`/admin/payments/${button.dataset.payment}/${action}`,{method:"POST",body:JSON.stringify({txid:txid||""})});adminToast(action==="confirm"?"Deposit confirmed":"Deposit cancelled");await loadAdmin();}catch(error){adminToast(error.message);}
  }));
  document.querySelectorAll("[data-withdrawal]").forEach(button=>button.addEventListener("click",async()=>{
    const action=button.dataset.action;
    const txid=action==="approve"?prompt("Payout transaction ID (optional):",""):"";
    const note=action==="reject"?prompt("Cancellation reason:","Rejected by administrator"):"";
    try{await adminApi(`/admin/withdrawals/${button.dataset.withdrawal}/${action}`,{method:"POST",body:JSON.stringify({txid:txid||"",note:note||""})});adminToast(action==="approve"?"Withdrawal approved":"Withdrawal rejected");await loadAdmin();}catch(error){adminToast(error.message);}
  }));
}
loadAdmin();
