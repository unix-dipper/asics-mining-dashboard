const http = require("node:http");
const fs = require("node:fs");
const path = require("node:path");
const crypto = require("node:crypto");
const { URL } = require("node:url");
const root = __dirname;
const dbPath = path.join(root, "data.json");
const mime = { ".html":"text/html; charset=utf-8", ".js":"text/javascript; charset=utf-8", ".css":"text/css; charset=utf-8", ".json":"application/json; charset=utf-8", ".svg":"image/svg+xml", ".png":"image/png", ".jpg":"image/jpeg" };
const hash = (password, salt = crypto.randomBytes(16).toString("hex")) => ({ salt, hash: crypto.scryptSync(password, salt, 64).toString("hex") });
const verify = (password, record) => crypto.timingSafeEqual(Buffer.from(hash(password, record.salt).hash, "hex"), Buffer.from(record.hash, "hex"));
const seed = { users: [{ id:1, email:"demo@asics.test", name:"Demo User", password:hash("demo123"), accountId:"0505050", uid:"8358-4562-4521", balance:400 }], sessions:{}, miners:[{id:1,name:"Antminer DR7 127 Th",price:10,total:19,daily:2.71,hashrate:127,icon:"▧"},{id:2,name:"Antminer KA3 (166 Th)",price:30,total:61,daily:3.39,hashrate:166,icon:"▦"},{id:3,name:"Bitmain Antminer S23",price:50,total:111,daily:3.7,hashrate:195,icon:"▥"},{id:4,name:"Bitmain Antminer S21 Pro",price:100,total:350,daily:11.67,hashrate:234,icon:"▤"}], contracts:[], transactions:[{id:1,userId:1,date:"2026-11-18",type:"Welcome bonus",amount:400,status:"completed"}], notifications:[{id:1,userId:1,title:"Welcome to ASICs Mining",message:"Your account is ready to start mining.",date:"Just now"}]};
let db; try { db = JSON.parse(fs.readFileSync(dbPath, "utf8")); } catch { db = seed; save(); }
function save() { fs.writeFileSync(dbPath, JSON.stringify(db, null, 2)); }
function send(res, status, body, headers = {}) { res.writeHead(status, { "Content-Type":"application/json; charset=utf-8", ...headers }); res.end(JSON.stringify(body)); }
function parseBody(req) { return new Promise((resolve, reject) => { let data=""; req.on("data", (chunk) => data += chunk); req.on("end", () => { try { resolve(data ? JSON.parse(data) : {}); } catch { reject(new Error("Invalid JSON")); } }); }); }
function publicUser(user) { const { password, ...safe } = user; safe.initials = user.name.split(/\s+/).map((part) => part[0]).join("").slice(0,2).toUpperCase(); return safe; }
function auth(req) { const token = (req.headers.authorization || "").replace("Bearer ", ""); const userId = db.sessions[token]; return db.users.find((user) => user.id === userId); }
function requireUser(req, res) { const user = auth(req); if (!user) { send(res, 401, { error:"Authentication required" }); return null; } return user; }
function routeApi(req, res, url) {
  const method = req.method; const bodyPromise = ["POST","PATCH"].includes(method) ? parseBody(req) : Promise.resolve({});
  bodyPromise.then((body) => {
    if (url.pathname === "/api/auth/login" && method === "POST") { const user = db.users.find((item) => item.email === body.email); if (!user || !verify(body.password || "", user.password)) return send(res, 401, { error:"Invalid credentials" }); const token = crypto.randomBytes(32).toString("hex"); db.sessions[token] = user.id; save(); return send(res, 200, { token }); }
    if (url.pathname === "/api/auth/register" && method === "POST") { if (!body.email || !body.password || !body.name) return send(res, 400, { error:"Name, email and password are required" }); if (db.users.some((item) => item.email === body.email)) return send(res, 409, { error:"Email already exists" }); const id = Math.max(...db.users.map((item) => item.id)) + 1; db.users.push({ id, email:body.email, name:body.name, password:hash(body.password), accountId:String(5000000 + id), uid:crypto.randomBytes(6).toString("hex").toUpperCase(), balance:0 }); save(); const token = crypto.randomBytes(32).toString("hex"); db.sessions[token] = id; save(); return send(res, 201, { token }); }
    const user = requireUser(req, res); if (!user) return;
    if (url.pathname === "/api/me" && method === "GET") return send(res, 200, { user:publicUser(user) });
    if (url.pathname === "/api/me" && method === "PATCH") { user.name = body.name || user.name; user.email = body.email || user.email; save(); return send(res, 200, { user:publicUser(user) }); }
    if (url.pathname === "/api/me/password" && method === "PATCH") { if (!verify(body.currentPassword || "", user.password)) return send(res, 400, { error:"Current password is incorrect" }); user.password = hash(body.newPassword); save(); return send(res, 200, { ok:true }); }
    if (url.pathname === "/api/dashboard") { const contracts = db.contracts.filter((item) => item.userId === user.id); return send(res, 200, { balance:user.balance, hashrate:contracts.reduce((sum, item) => sum + item.hashrate, 0), dailyProfit:contracts.reduce((sum, item) => sum + item.daily, 0), totalProfit:contracts.reduce((sum, item) => sum + item.total, 0) }); }
    if (url.pathname === "/api/miners") return send(res, 200, db.miners);
    if (url.pathname === "/api/contracts" && method === "GET") return send(res, 200, db.contracts.filter((item) => item.userId === user.id));
    if (url.pathname === "/api/contracts/purchase" && method === "POST") { const miner = db.miners.find((item) => item.id === Number(body.minerId)); if (!miner) return send(res, 404, { error:"Miner not found" }); if (user.balance < miner.price) return send(res, 400, { error:"Insufficient balance" }); user.balance -= miner.price; const contract = { id:Date.now(), userId:user.id, minerId:miner.id, name:miner.name, price:miner.price, total:miner.total, daily:miner.daily, hashrate:miner.hashrate, icon:miner.icon, status:"active" }; db.contracts.push(contract); db.transactions.push({ id:Date.now()+1, userId:user.id, date:new Date().toISOString().slice(0,10), type:`Contract: ${miner.name}`, amount:-miner.price, status:"completed" }); save(); return send(res, 201, contract); }
    if (url.pathname === "/api/transactions") return send(res, 200, db.transactions.filter((item) => item.userId === user.id));
    if (url.pathname === "/api/notifications") return send(res, 200, db.notifications.filter((item) => item.userId === user.id));
    return send(res, 404, { error:"Not found" });
  }).catch((error) => send(res, 400, { error:error.message }));
}
const server = http.createServer((req, res) => { const url = new URL(req.url, `http://${req.headers.host || "localhost"}`); if (url.pathname.startsWith("/api/")) return routeApi(req, res, url); let file = url.pathname === "/" ? "index.html" : url.pathname.slice(1); const filePath = path.resolve(root, file); if (!filePath.startsWith(root) || !fs.existsSync(filePath) || fs.statSync(filePath).isDirectory()) file = "index.html"; const finalPath = path.join(root, file); res.writeHead(200, { "Content-Type":mime[path.extname(finalPath)] || "application/octet-stream" }); fs.createReadStream(finalPath).pipe(res); });
const port = Number(process.env.PORT || 4173); server.listen(port, () => console.log(`ASICs Mining running at http://localhost:${port}`));
