const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function element(value = '') {
    return { value, disabled: false, style: {}, innerHTML: '', textContent: '', listeners: {}, attributes: {},
        addEventListener(type, fn) { (this.listeners[type] ||= []).push(fn); },
        setAttribute(name, value) { this.attributes[name] = value; },
        removeAttribute(name) { delete this.attributes[name]; },
        querySelector() { return this.spinner ||= element(); } };
}
async function loginScenario(result) {
    const elements = Object.fromEntries(['loginForm','loginBtn','username','password'].map(id => [id, element()]));
    elements.username.value = 'fixture'; elements.password.value = 'synthetic';
    let resolveFetch, calls = 0;
    const context = vm.createContext({ document: { getElementById: id => elements[id], addEventListener() {} },
        fetch: () => { calls++; return new Promise(resolve => { resolveFetch = resolve; }); },
        Swal: { fire: () => Promise.resolve({}), showLoading() {} }, window: { location: {} }, console,
        AbortController, setTimeout, clearTimeout });
    vm.runInContext(fs.readFileSync('assets/js/login.js','utf8'), context);
    const first = context.handleLoginForm({ preventDefault() {} });
    assert.equal(elements.loginBtn.disabled, true, 'login submit must be busy during request');
    const second = context.handleLoginForm({ preventDefault() {} });
    assert.equal(calls, 1, 'a second submit must not send duplicate credentials');
    resolveFetch(result);
    await Promise.all([first, second]);
    assert.equal(elements.loginBtn.disabled, false, 'login failure must allow another attempt');
    assert.equal(elements.loginBtn.querySelector().style.display, 'none');
    assert.equal(elements.username.value, 'fixture', 'failure keeps entered username');
    assert.equal(context.window.location.href, undefined, 'failed login cannot navigate');
}
(async () => {
    await loginScenario({ ok: true, json: async () => ({ status: 'error', message: 'invalid' }) });
    await loginScenario({ ok: false, json: async () => { throw new Error('invalid JSON'); } });
    const root = element();
    const ctx = vm.createContext({ document: { addEventListener() {}, getElementById: id => id === 'employeeDashboardContainer' ? root : null },
        escapeHtml: s => String(s ?? ''), fetch: async () => ({ json: async () => ({ status: 'error' }) }), console });
    vm.runInContext(fs.readFileSync('assets/js/dashboard.js', 'utf8'), ctx);
    for (const [status, label] of Object.entries({ pending_manager: 'รอหัวหน้างานอนุมัติ', pending_hr: 'รอ HR อนุมัติ', pending_cancel_hr: 'รอ HR/Admin อนุมัติยกเลิก' })) {
        assert.ok(ctx.getLeaveStatusBadge(status).includes(label), `${status} needs a Thai label`);
    }
    await ctx.loadDashboardData();
    assert.match(root.innerHTML, /ลองใหม่/, 'API errors must offer a visible retry');
    assert.equal(root.querySelector().listeners.click.length, 1, 'retry must actually be connected');
    ctx.fetch = async () => ({ json: async () => ({ status: 'success', data: { personal_dashboard: { leave_summary: { pending: 6, pending_cancel_hr: 5 } } } }) });
    await root.querySelector().listeners.click[0]();
    assert.match(root.innerHTML, /รออนุมัติยกเลิก/);
    assert.match(root.innerHTML, /<strong>5<\/strong>/);
    console.log('PASS login recovery and dashboard status/retry behavior');
})().catch(error => { console.error(error); process.exitCode = 1; });