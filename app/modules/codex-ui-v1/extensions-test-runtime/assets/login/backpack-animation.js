export const GREETING = Object.freeze([
  {frame:0,ms:1500},{frame:1,ms:160},{frame:2,ms:500},{frame:3,ms:280},
  {frame:4,ms:280},{frame:5,ms:420},{frame:6,ms:340},{frame:7,ms:160},
  {frame:8,ms:320},{frame:9,ms:900},
]);
export const IDLE = Object.freeze([{frame:13,ms:3500},{frame:15,ms:140},{frame:13,ms:2200},{frame:14,ms:3200},{frame:13,ms:2600}]);
export const SCRATCH = Object.freeze([{frame:10,ms:350},{frame:11,ms:700},{frame:12,ms:350},{frame:0,ms:1200}]);

export function validateBackpackManifest(value){
  if(value?.version!==3||value.width!==1024||value.height!==1536||value.frames?.length!==16)throw new Error('Invalid greeting manifest');
  value.frames.forEach((frame,i)=>{
    if(frame.file!==`pose-${String(i+1).padStart(2,'0')}.png`||!/^[a-f0-9]{64}$/.test(frame.sha256))throw new Error('Invalid greeting frame');
  });
  return value;
}

export async function mountBackpackGreeting(stage,form){
  const reduced=matchMedia('(prefers-reduced-motion: reduce)');
  const fallback=stage.querySelector('img');
  if(reduced.matches){stage.dataset.engine='backpack-static';return {destroy(){},stop(){}};}
  const base=new URL('./jaspe-backpack-v2/',import.meta.url);
  const abort=new AbortController();const response=await fetch(new URL('manifest.json',base),{signal:abort.signal});
  if(!response.ok)throw new Error('Greeting unavailable');
  const manifest=validateBackpackManifest(await response.json());
  const frames=[];
  // Decode at half resolution to limit memory on phones.
  try{
    for(const frame of manifest.frames){
      const result=await fetch(new URL(frame.file,base),{signal:abort.signal});if(!result.ok)throw new Error('Greeting image unavailable');
      const bytes=await result.arrayBuffer();const digest=Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256',bytes)),b=>b.toString(16).padStart(2,'0')).join('');
      if(digest!==frame.sha256)throw new Error('Greeting image integrity failed');
      frames.push(await createImageBitmap(new Blob([bytes],{type:'image/png'}),{resizeWidth:512,resizeHeight:768,resizeQuality:'high'}));
    }
  }catch(error){frames.forEach(frame=>frame.close());throw error;}
  const canvas=document.createElement('canvas');canvas.width=512;canvas.height=768;canvas.setAttribute('aria-hidden','true');
  const context=canvas.getContext('2d');if(!context){frames.forEach(frame=>frame.close());throw new Error('Greeting canvas unavailable');}
  stage.append(canvas);fallback.hidden=true;stage.dataset.engine='backpack';
  const sway=canvas.animate([{transform:'translate(0,0) rotate(-.2deg)'},{transform:'translate(1px,1px) rotate(.25deg)'},{transform:'translate(0,0) rotate(-.2deg)'}],{duration:6800,iterations:Infinity,easing:'ease-in-out'});sway.pause();
  let timer=0,raf=0,step=0,current=0,phase='greeting',idleCycles=0,closed=false,onScreen=true;
  const typing=()=>form.contains(document.activeElement)&&['INPUT','TEXTAREA'].includes(document.activeElement?.tagName);
  const visible=()=>!closed&&!document.hidden&&onScreen&&!reduced.matches;
  function paint(index,alpha=1){context.globalAlpha=alpha;context.drawImage(frames[index],0,0);}
  function draw(index,blend=false){
    const previous=current;cancelAnimationFrame(raf);
    stage.dataset.frame=String(index+1);current=index;
    if(blend&&previous!==index){
      const start=performance.now();const render=now=>{const t=Math.min(1,(now-start)/90);context.clearRect(0,0,512,768);paint(previous,1-t);paint(index,t);context.globalAlpha=1;if(t<1)raf=requestAnimationFrame(render);};raf=requestAnimationFrame(render);
    }else{context.clearRect(0,0,512,768);paint(index);}
  }
  function stop(){clearTimeout(timer);cancelAnimationFrame(raf);timer=0;raf=0;sway.pause();}
  function advance(){
    stop();if(!visible())return;
    if(typing()){draw(0);stage.dataset.motion='attentive';timer=setTimeout(advance,800);return;}
    const timeline=phase==='greeting'?GREETING:phase==='scratch'?SCRATCH:IDLE;
    stage.dataset.motion=phase;phase==='idle'?sway.play():sway.pause();
    const entry=timeline[step];draw(entry.frame,phase!=='idle');step++;
    if(step===timeline.length){step=0;if(phase==='greeting'||phase==='scratch')phase='idle';else{idleCycles++;phase=idleCycles%2===0?'scratch':'idle';}}
    timer=setTimeout(advance,entry.ms);
  }
  function resume(){stop();if(reduced.matches){draw(0);return;}if(visible()){if(typing()){phase='idle';step=0;}advance();}}
  function onFocus(){stop();phase='idle';step=0;draw(0);if(visible())timer=setTimeout(advance,800);}
  const observer=new IntersectionObserver(entries=>{onScreen=entries[0].isIntersecting;resume();});observer.observe(stage);
  document.addEventListener('visibilitychange',resume);form.addEventListener('focusin',onFocus);form.addEventListener('focusout',resume);form.addEventListener('submit',stop);
  reduced.addEventListener('change',resume);
  draw(0);advance();
  return {stop,destroy(){closed=true;stop();sway.cancel();abort.abort();observer.disconnect();document.removeEventListener('visibilitychange',resume);form.removeEventListener('focusin',onFocus);form.removeEventListener('focusout',resume);form.removeEventListener('submit',stop);reduced.removeEventListener('change',resume);frames.forEach(frame=>frame.close());canvas.remove();fallback.hidden=false;}};
}
