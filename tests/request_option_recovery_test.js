const fs=require('node:fs'); const vm=require('node:vm'); const assert=require('node:assert/strict');
function element(value='') { return {value,disabled:false,innerHTML:'',textContent:'',listeners:{},dataset:{},classList:{add(){},remove(){},toggle(){}},setAttribute(){},
 addEventListener(type,fn){(this.listeners[type] ||= []).push(fn);},querySelector(){return this.button ||= element();},querySelectorAll(){return [];} }; }
const tick=()=>new Promise(resolve=>setImmediate(resolve));
(async()=>{
 const elements=Object.fromEntries(['leaveTypeSelect','leaveTypeIconGrid','leaveUsageSummaryGrid','leaveSubmitButton'].map(id=>[id,element()]));
 const ctx=vm.createContext({document:{getElementById:id=>elements[id]||null,addEventListener(){},querySelectorAll(){return[];}},
 fetch:async()=>({json:async()=>({status:'error'})}),console,escapeHtml:s=>String(s),escapeAttr:s=>String(s),setTimeout,clearTimeout});
 vm.runInContext(fs.readFileSync('assets/js/leave_request.js','utf8'),ctx);
 ctx.updateLeaveRequestMode=()=>{}; ctx.updateLeaveTypeCondition=()=>{};
 await ctx.loadLeaveOptions();
 assert.match(elements.leaveTypeIconGrid.innerHTML,/ลองใหม่/,'self-service leave options must expose a retry');
 assert.equal(elements.leaveSubmitButton.disabled,true,'cannot submit before required options are ready');
 ctx.fetch=async()=>({json:async()=>({status:'success',data:[{id:1,type_name:'ลาทดสอบ'}]})});
 await elements.leaveTypeIconGrid.querySelector().listeners.click[0]();
 assert.match(elements.leaveTypeIconGrid.innerHTML,/ลาทดสอบ/);
 assert.equal(elements.leaveSubmitButton.disabled,false);
 ctx.fetch=async()=>({json:async()=>({status:'success',data:[]})});
 await ctx.loadLeaveOptions();
 assert.match(elements.leaveTypeIconGrid.innerHTML,/ยังไม่มีประเภทการลา/,'empty result is different from loading/failure');
 ctx.fetch=async()=>{throw Error('offline');}; await ctx.loadLeaveUsageSummary();
 assert.match(elements.leaveUsageSummaryGrid.innerHTML,/ลองใหม่/);
 ctx.renderProjectedLeaveUsageSummary();
 assert.match(elements.leaveUsageSummaryGrid.innerHTML,/ลองใหม่/,'editing the form must not erase usage retry');
 const proxyIds=['proxyEmployeeId','proxyTargetEmployeeId','proxyLeaveTypeId','proxyActivityTypeId','proxyEmployeeLoadStatus','proxyLeaveTypeLoadStatus','proxyActivityTypeLoadStatus'];
 const proxyElements=Object.fromEntries(proxyIds.map(id=>[id,element()]));
 const proxyCtx=vm.createContext({document:{getElementById:id=>proxyElements[id]||null,querySelectorAll:()=>[],querySelector:()=>null},
 window:{},fetch:async()=>{throw Error('offline');},console,Swal:{fire(){throw Error('load failures should be inline and recoverable');}}});
 vm.runInContext(fs.readFileSync('assets/js/proxy_request.js','utf8'),proxyCtx); await tick();
 for(const id of ['proxyEmployeeLoadStatus','proxyLeaveTypeLoadStatus','proxyActivityTypeLoadStatus']) assert.match(proxyElements[id].innerHTML,/ลองใหม่/,id);
 proxyCtx.fetch=async()=>({json:async()=>({status:'success',data:[{id:1,type_name:'ตัวเลือกทดสอบ',first_name_th:'Fixture',last_name_th:'Employee'}]})});
 for(const id of ['proxyEmployeeLoadStatus','proxyLeaveTypeLoadStatus','proxyActivityTypeLoadStatus']) await proxyElements[id].querySelector().listeners.click[0]();
 for(const id of ['proxyEmployeeId','proxyLeaveTypeId','proxyActivityTypeId']) assert.equal(proxyElements[id].disabled,false,id+' recovers');
 console.log('PASS leave and proxy option failure, empty state and retry recovery');
})().catch(error=>{console.error(error);process.exitCode=1;});