'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const {readFileSync}=require('node:fs');
const path=require('node:path');
const id='04286836-0c33-4744-aec2-dfab27c15f22';
function element(){return {children:[],dataset:{},listeners:{},hidden:false,textContent:'',
 append(child){this.children.push(child);},replaceChildren(){this.children=[];},
 addEventListener(name,fn){this.listeners[name]=fn;},setAttribute(){}};}
async function view(rows,ok=true){
 const nodes={notifications:element(),state:element(),retry:element()};let fetches=0,sends=0;
 const window={Notification:function(){sends++;}};
 const context=vm.createContext({window,Intl,Date,Set,fetch:async()=>{
  fetches++;return {ok,status:ok?200:403,json:async()=>({data:{notifications:rows}})};
 },document:{hidden:false,getElementById:id=>nodes[id],createElement:element,
  querySelectorAll:()=>[],addEventListener(){}},setInterval(){}});
 for(const file of ['parent-notification-data.js','parent-notifications-push.js','parent-notifications-view.js']) {
  vm.runInContext(readFileSync(path.join(__dirname,file),'utf8'),context,{filename:file});
 }
 await new Promise(resolve=>setImmediate(resolve));
 return {nodes,fetches,sends};
}
test('Rosario view loads normalized inbox without requesting or sending push',async()=>{
 const at='2026-10-12T12:00:00Z';
 const r=await view([{id,student_id:id,student_name:'<script>TEST</script>',event_type:'student.arrival',
  created_at:at,payload:{occurredAt:at}}]);
 assert.equal(r.fetches,1);assert.equal(r.sends,0);assert.equal(r.nodes.notifications.children.length,1);
 assert.ok(r.nodes.notifications.children[0].children[0].textContent.includes('<script>TEST</script>'));
 assert.equal(r.nodes.state.textContent,'');
});
test('malformed inbox and parent refusal clear the view and expose retry',async()=>{
 for(const r of [await view([{}]),await view([],false)]) {
  assert.equal(r.nodes.notifications.children.length,0);assert.equal(r.nodes.retry.hidden,false);
  assert.equal(r.sends,0);assert.ok(r.nodes.state.textContent.length>0);
 }
});
