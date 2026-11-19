const adminToast=(message)=>{const el=document.querySelector("#toast");el.textContent=message;el.classList.add("show");setTimeout(()=>el.classList.remove("show"),2400)};
const adminEsc=(value)=>String(value??"").replace(/[&<>"']/g,(char)=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[char]));
const formatAdminIp=(ip)=>{const value=String(ip||"unknown");return ["127.0.0.1","::1","localhost"].includes(value)?`${value} (Localhost)`:value};
async function adminApi(path,options={}){const response=await fetch(`api.php/${String(path).replace(/^\/+/,"")}`,{...options,headers:{"Content-Type":"application/json",...(options.headers||{})}});const body=await response.json().catch(()=>({}));if(!response.ok)throw new Error(body.error||"Request failed");return body;}
function adminShell(data){
  return `<aside class="admin-sidebar"><div class="admin-logo"><div class="brand-mark">₿</div><div><strong>ASICs Mining</strong><span>Administration</span></div></div><nav><button class="active" data-admin-section="overview">Overview</button><button data-admin-section="users">Users</button><button data-admin-section="payments">Deposits</button><button data-admin-section="withdrawals">Withdrawals</button><button data-admin-section="products">Plans</button><button data-admin-section="messages">Contact</button><button data-admin-section="audit">Audit log</button></nav><a href="/" class="admin-back">← Website</a></aside><main class="admin-main"><header class="admin-header"><div><span>Administration</span><h1 id="adminTitle">Overview</h1></div><div class="admin-profile"><div class="avatar">AD</div><div><b>${adminEsc(data.user.name)}</b><small>Administrator</small></div></div></header><div id="adminContent">${adminOverview(data)}</div></main>`;
}
function adminOverview(data){const o=data.overview;return `<div class="admin-stat-grid"><article><span>Total users</span><strong>${o.users}</strong></article><article><span>Paid deposits</span><strong>$${Number(o.paidPayments).toFixed(2)}</strong></article><article><span>Pending deposits</span><strong>$${Number(o.pendingPayments).toFixed(2)}</strong></article><article><span>Pending withdrawals</span><strong>$${Number(o.pendingWithdrawals).toFixed(2)}</strong></article></div><div class="admin-panels"><section><h2>Recent users</h2>${adminUsersTable(data.users.slice(0,6))}</section><section><h2>Recent deposits</h2>${adminPaymentsTable(data.payments.slice(0,8))}</section></div>`}
function adminUsersTable(rows){return `<div class="admin-table-wrap"><table><thead><tr><th>User</th><th>Email</th><th>Deposited</th><th>Making / day</th><th>Balance</th><th>VIP</th><th>Status</th><th>Devices / IP</th><th>Actions</th></tr></thead><tbody>${rows.map(r=>`<tr><td>${adminEsc(r.name)}<small>${adminEsc(r.accountId)}</small></td><td>${adminEsc(r.email)}</td><td>$${Number(r.deposited).toFixed(2)}</td><td>$${Number(r.earning||0).toFixed(2)}</td><td>$${Number(r.balance).toFixed(2)}</td><td>${r.vipUntil&&new Date(r.vipUntil).getTime()>Date.now()?adminEsc(r.vipUntil):"—"}</td><td><span class="admin-status ${adminEsc(r.status||"active")}">${adminEsc(r.status||"active")}</span></td><td>${(r.devices||[]).map(d=>{const ip=d.ip_address||"unknown";const local=["127.0.0.1","::1","localhost"].includes(ip);const agent=d.user_agent||"Unknown device";return `<span class="device-chip" title="${adminEsc(agent)} | First: ${adminEsc(d.first_seen)} | Last: ${adminEsc(d.last_seen)}">${local?"Localhost":adminEsc(ip)}<small>${adminEsc(agent.slice(0,32))}</small></span>`}).join("")||"—"}</td><td>${r.isAdmin?"—":`${r.status==="suspended"?`<button class="admin-confirm" data-user="${r.id}" data-action="activate">Activate</button>`:`<button class="admin-cancel" data-user="${r.id}" data-action="suspend">Suspend</button>`}<button class="admin-confirm" data-user="${r.id}" data-action="adjust">Adjust</button>`}</td></tr>`).join("")}</tbody></table></div>`}
function adminPaymentsTable(rows){return `<div class="admin-table-wrap"><table><thead><tr><th>User</th><th>Plan</th><th>Network</th><th>Amount</th><th>Created</th><th>User confirmed</th><th>Status</th><th>Action</th></tr></thead><tbody>${rows.map(r=>`<tr><td>${adminEsc(r.name)}<small>${adminEsc(r.email)}</small></td><td>${adminEsc(r.product_name)}</td><td>${adminEsc(r.network)}</td><td>$${Number(r.amount_usd).toFixed(2)}</td><td>${adminEsc(r.created_at)}</td><td>${r.confirmed_at?adminEsc(r.confirmed_at):"—"}</td><td><span class="admin-status ${adminEsc(r.status)}">${adminEsc(r.status)}</span></td><td>${r.status==="pending"||r.status==="review"?`<button class="admin-confirm" data-payment="${r.id}" data-action="confirm">Confirm</button><button class="admin-cancel" data-payment="${r.id}" data-action="cancel">Cancel</button>`:"—"}</td></tr>`).join("")}</tbody></table></div>`}
function adminWithdrawalsTable(rows){return `<div class="admin-table-wrap"><table><thead><tr><th>User</th><th>Coin</th><th>Amount</th><th>Destination</th><th>Status</th><th>Action</th></tr></thead><tbody>${rows.map(r=>`<tr><td>${adminEsc(r.name)}<small>${adminEsc(r.email)}</small></td><td>${adminEsc(r.coin)}</td><td>$${Number(r.amount_usd).toFixed(2)}</td><td class="admin-address">${adminEsc(r.address)}</td><td><span class="admin-status ${adminEsc(r.status)}">${adminEsc(r.status)}</span></td><td>${r.status==="pending"?`<button class="admin-confirm" data-withdrawal="${r.id}" data-action="approve">Approve</button><button class="admin-cancel" data-withdrawal="${r.id}" data-action="reject">Reject</button>`:"—"}</td></tr>`).join("")}</tbody></table></div>`}
function adminProductsTable(rows){
  return `<button class="admin-confirm admin-add-product" id="addProduct">+ Add product</button><div class="admin-table-wrap"><table><thead><tr><th>Name</th><th>Price</th><th>Daily</th><th>Return</th><th>Duration</th><th>Hashrate</th><th>VIP</th><th>Status</th><th>Actions</th></tr></thead><tbody>${rows.map(p=>`<tr><td>${adminEsc(p.name)}</td><td>$${Number(p.price_usd).toFixed(2)}</td><td>$${Number(p.daily_profit_usd).toFixed(2)}</td><td>$${Number(p.total_return_usd).toFixed(2)}</td><td>${Number(p.duration_days||30)} days</td><td>${Number(p.hashrate).toFixed(0)} TH/s</td><td>${p.vip_only?"VIP only":"Public"}</td><td>${p.active?"Active":"Inactive"}</td><td><button class="admin-confirm" data-edit-product="${p.id}">Edit</button><button class="admin-cancel" data-delete-product="${p.id}">Remove</button></td></tr>`).join("")}</tbody></table></div>`;
}
async function loadAdmin(){
  try{
    const me=await adminApi("/me");
    if(!me.user.isAdmin){renderAdminLogin("This account does not have administrator access.");return;}
    const [overview,users,payments,withdrawals,products,messages,audit]=await Promise.all([adminApi("/admin/overview"),adminApi("/admin/users"),adminApi("/admin/payments"),adminApi("/admin/withdrawals"),adminApi("/admin/products"),adminApi("/admin/messages"),adminApi("/admin/audit")]);
    const data={user:me.user,overview,users,payments,withdrawals,products,messages,audit};
    document.querySelector("#adminApp").innerHTML=adminShell(data);
    bindAdmin(data);
  }catch(error){renderAdminLogin(error.message);}
}
function renderAdminLogin(message=""){
  document.querySelector("#adminApp").innerHTML=`<main class="admin-login-page"><form class="admin-login-card"><div class="admin-logo"><div class="brand-mark">₿</div><div><strong>ASICs Mining</strong><span>Administration</span></div></div><h1>Administrator sign in</h1><p>${adminEsc(message)}</p><label>Email<input name="email" type="email" autocomplete="username" required /></label><label>Password<input name="password" type="password" autocomplete="current-password" required /></label><button type="submit">Sign in</button><a href="/">← Back to website</a></form></main>`;
  document.querySelector(".admin-login-card").addEventListener("submit",async(event)=>{
    event.preventDefault();
    const data=Object.fromEntries(new FormData(event.currentTarget));
    try{
      const result=await adminApi("/auth/login",{method:"POST",body:JSON.stringify(data)});
      if(!result.user.isAdmin){renderAdminLogin("This account does not have administrator access.");return;}
      await loadAdmin();
    }catch(error){renderAdminLogin(error.message);}
  });
}
function bindAdmin(data){
  window.adminData=data;
  document.querySelectorAll("[data-admin-section]").forEach(button=>button.addEventListener("click",()=>{
    document.querySelectorAll("[data-admin-section]").forEach(item=>item.classList.remove("active"));button.classList.add("active");
    const section=button.dataset.adminSection;
    const content=document.querySelector("#adminContent"),title=document.querySelector("#adminTitle");
    const names={overview:"Overview",users:"Users",payments:"Deposits",withdrawals:"Withdrawals",products:"Mining plans",messages:"Contact",audit:"Audit log"};
    title.textContent=names[section];
    content.innerHTML=section==="overview"?adminOverview(data):section==="users"?`<section class="admin-panel-full"><h2>All users</h2>${adminUsersTable(data.users)}</section>`:section==="payments"?`<section class="admin-panel-full"><h2>Deposit requests</h2>${adminPaymentsTable(data.payments)}</section>`:section==="withdrawals"?`<section class="admin-panel-full"><h2>Withdrawal requests</h2>${adminWithdrawalsTable(data.withdrawals)}</section>`:section==="messages"?`<section class="admin-panel-full"><h2>Contact messages</h2><div class="admin-table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Message</th><th>Date</th></tr></thead><tbody>${data.messages.map(m=>`<tr><td>${adminEsc(m.name)}</td><td>${adminEsc(m.email)}</td><td>${adminEsc(m.message)}</td><td>${adminEsc(m.created_at)}</td></tr>`).join("")}</tbody></table></div></section>`:section==="audit"?`<section class="admin-panel-full"><h2>Account and administrator audit log</h2><div class="admin-table-wrap"><table><thead><tr><th>Date</th><th>Actor</th><th>User</th><th>Action</th><th>IP</th><th>Details</th></tr></thead><tbody>${data.audit.map(r=>`<tr><td>${adminEsc(r.created_at)}</td><td>${adminEsc(r.actor_name||"System")}</td><td>${adminEsc(r.user_name||"—")}</td><td>${adminEsc(r.action)}</td><td>${adminEsc(formatAdminIp(r.ip_address))}</td><td>${adminEsc(r.details||"")}</td></tr>`).join("")}</tbody></table></div></section>`:`<section class="admin-panel-full"><h2>${names[section]}</h2>${adminProductsTable(data.products)}</section>`;
    bindActions();
  }));
  bindActions();
}
function bindActions(){
  const add=document.querySelector("#addProduct");
  if(add) add.addEventListener("click",async()=>{
    const name=prompt("Product name","");
    if(!name)return;
    const price=Number(prompt("Price USD","99"));
    const daily=Number(prompt("Daily profit USD","3"));
    const hashrate=Number(prompt("Hashrate TH/s","100"));
    const durationDays=Number(prompt("Duration in days (7, 15, or 30)","30"));
    const total=Number(prompt("Total return USD",String(price*3)));
    const vip=confirm("Is this a VIP-only product?");
    try{await adminApi("/admin/products",{method:"POST",body:JSON.stringify({name,price,daily,hashrate,total,durationDays,vipOnly:vip?1:0,active:1})});adminToast("Product added");await loadAdmin();}catch(error){adminToast(error.message);}
  });
  document.querySelectorAll("[data-edit-product]").forEach(button=>button.addEventListener("click",async()=>{
    const id=button.dataset.editProduct;
    const row=(window.adminData?.products||[]).find(p=>String(p.id)==String(id)); if(!row)return;
    const name=prompt("Product name",row.name); if(name===null)return;
    const price=Number(prompt("Price USD",row.price_usd));
    const daily=Number(prompt("Daily profit USD",row.daily_profit_usd));
    const hashrate=Number(prompt("Hashrate TH/s",row.hashrate));
    const durationDays=Number(prompt("Duration in days (7, 15, or 30)",row.duration_days||30));
    const total=Number(prompt("Total return USD",row.total_return_usd));
    const vip=confirm("VIP-only product?");
    const active=confirm("Keep this product active?");
    try{await adminApi("/admin/products",{method:"PATCH",body:JSON.stringify({id:Number(id),name,price,daily,hashrate,total,durationDays,vipOnly:vip?1:0,active:active?1:0})});adminToast("Product updated");await loadAdmin();}catch(error){adminToast(error.message);}
  }));
  document.querySelectorAll("[data-delete-product]").forEach(button=>button.addEventListener("click",async()=>{
    if(!confirm("Remove this product from the store?"))return;
    try{await adminApi(`/admin/products/${button.dataset.deleteProduct}`,{method:"DELETE"});adminToast("Product removed");await loadAdmin();}catch(error){adminToast(error.message);}
  }));
  document.querySelectorAll("[data-user]").forEach(button=>button.addEventListener("click",async()=>{
    const action=button.dataset.action;
    if(action==="adjust"){
      const amount=prompt("Balance adjustment (+ or - amount):","0");
      const reason=prompt("Reason:", "Admin balance adjustment");
      if(amount===null)return;
      try{await adminApi(`/admin/users/${button.dataset.user}/adjust`,{method:"POST",body:JSON.stringify({amount:Number(amount),reason:reason||""})});adminToast("Balance adjusted");await loadAdmin();}catch(error){adminToast(error.message);}
    }else{
      const note=action==="suspend"?prompt("Suspension reason:","Suspended by administrator"):"";
      try{await adminApi(`/admin/users/${button.dataset.user}/${action}`,{method:"POST",body:JSON.stringify({note:note||""})});adminToast(action==="suspend"?"Account suspended":"Account activated");await loadAdmin();}catch(error){adminToast(error.message);}
    }
  }));
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
