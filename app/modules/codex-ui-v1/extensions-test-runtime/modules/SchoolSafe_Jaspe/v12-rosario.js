import {mountLiveCompanion} from './jaspe2d/modules/jaspe2d/live-companion.js?v=gestes-4';
import {createPresentationController} from './jaspe2d/modules/jaspe2d/presentation-controller.js?v=gestes-4';

const host=document.getElementById('jaspe-native');
const stage=document.getElementById('jaspe-stage');
const fallback=window.SchoolSafeJaspe2d;
if(host&&stage&&fallback) {
  let live=null;
  const isVisible=()=>{
    if (!stage.isConnected || document.hidden) return false;
    if (window.parent !== window) {
      try {
        const dialog=window.parent.document.getElementById('ss-jaspe-dialog');
        if (dialog && !dialog.open) return false;
      } catch (_) { return false; }
    }
    return true;
  };
  const presentation=createPresentationController({
    createPrimary:async ({isVisible})=>{
      live=await mountLiveCompanion(stage,host,{
        isVisible, isBust:()=>true, activityTarget:host, idleLiveliness:.65,
        allowIdleMotion:()=>presentation.getState().current.kind==='idle',
        isTyping:()=>host.contains(document.activeElement)&&['INPUT','TEXTAREA'].includes(document.activeElement?.tagName),
      });
      host.dataset.jaspeEngine='v12';
      document.getElementById('jaspe-fallback').hidden=true;
      return {
        play:command=>live.play(command.action,{intensity:command.intensity}),
        stop:()=>live.stop(),
        destroy:()=>{live.destroy();host.dataset.jaspeEngine='webp';document.getElementById('jaspe-fallback').hidden=false;},
      };
    },
    createFallback:()=>({play:()=>true,stop:()=>true}),
  });
  const question=document.getElementById('jaspe-question');
  const intents={IDLE:'idle',LISTENING:'listen',THINKING:'think',SPEAKING:'explain',ERROR:'error',OFFLINE:'refuse'};
  // A requested emotion/gesture keeps the stage for its duration; otherwise the speech
  // state would immediately replace it with 'explain' and the gesture would never be seen.
  let motionUntil=0;
  const motionGuarded=()=>performance.now()<motionUntil;
  fallback.onStateChange(state=>{
    const inputIdle=state==='IDLE' && document.activeElement===question;
    const kind=inputIdle?'listen':intents[state]||'idle';
    if(motionGuarded()&&['idle','think','explain','speak'].includes(kind))return;
    host.dataset.motionIntent=kind;
    presentation.dispatch({kind,source:inputIdle?'input':'conversation',replace:true,holdMs:0});
  });
  function focusQuestion(){
    if (['idle','listen'].includes(presentation.getState().current.kind)) {
      host.dataset.motionIntent='listen';
      presentation.dispatch({kind:'listen',source:'input',replace:true,holdMs:0});
    }
  }
  question?.addEventListener('focus',focusQuestion);
  question?.addEventListener('blur',()=>{
    if(presentation.getState().current.source==='input'){
      host.dataset.motionIntent='idle';
      presentation.dispatch({kind:'idle',source:'input',replace:true});
    }
  });
  // Emotions and gestures requested by the conversation (jaspe-chat.php -> assistant.js).
  host.addEventListener('jaspe-motion',event=>{
    const kind=event?.detail?.kind;
    if(typeof kind!=='string'||!kind)return;
    if(presentation.dispatch({kind,source:'conversation',replace:true})){
      motionUntil=performance.now()+(Number(event.detail.holdMs)||4200);
      host.dataset.motionIntent=kind;
    }
  });
  await presentation.mount({host,surface:'workspace-avatar',isVisible});
  host.jaspeV12={presentation,getLive:()=>live};
  if(document.activeElement===question)focusQuestion();
  else if (live && presentation.getState().current.kind==='idle') await live.play('wave',{intensity:.45});
  window.addEventListener('pagehide',()=>presentation.destroy(),{once:true});
}
