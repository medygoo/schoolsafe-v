// Time is the visible animation clock, so hidden tabs never accumulate idle acts.
export const waitingActivities = Object.freeze([
  {id:'leanPanel', label:'S’adosser au panneau', fullBody:true, status:'key-pose-draft'},
  {id:'walk', label:'Quelques pas sur place', fullBody:true, status:'existing-prototype', automatic:false},
  {id:'standRelaxed', label:'Se redresser doucement', fullBody:true, status:'frames-needed'},
  {id:'sitChair', label:'S’asseoir puis se relever', fullBody:true, status:'key-pose-draft'},
  {id:'readBook', label:'Ouvrir, lire et refermer le livre', fullBody:false, status:'key-pose-draft'},
  {id:'joySway', label:'Attendre avec un léger balancement', fullBody:true, status:'existing-prototype'},
  {id:'attentive', label:'Attendre avec attention', fullBody:false, status:'existing-prototype'},
]);

// Gestures already animated by the v12 engine and suitable while waiting.
// 2026-10-17: the idle loop used to reach only joySway and attentive, so JASPE
// looked frozen between replies. Weight duplicates an entry in the rotation.
export const idleGestures = Object.freeze([
  {id:'joySway', fullBody:true, weight:2},
  {id:'attentive', fullBody:false, weight:2},
  {id:'fidget', fullBody:false, weight:1},
  {id:'hips', fullBody:false, weight:1},
  {id:'balance', fullBody:true, weight:1},
  {id:'heelPlay', fullBody:true, weight:1},
  {id:'toePlay', fullBody:true, weight:1},
  {id:'sideStep', fullBody:true, weight:1},
  {id:'curtsy', fullBody:true, weight:1},
  {id:'noseScratch', fullBody:false, weight:1},
  {id:'yes', fullBody:false, weight:1},
  {id:'no', fullBody:false, weight:1},
  {id:'wink', fullBody:false, weight:1},
  {id:'clap', fullBody:false, weight:1},
  {id:'footTap', fullBody:false, weight:1},
]);

const rotation = [];
for (const item of idleGestures) {
  for (let copy = 0; copy < item.weight; copy++) rotation.push(item);
}

export class IdlePlanner {
  constructor(){this.lastActivity=0;this.nextAt=1700;this.welcomed=false;this.cursor=0;}
  activity(clock){this.welcomed=true;this.lastActivity=clock;this.nextAt=Math.max(this.nextAt,clock+9000);}
  next({clock,typing=false,idle=true,bust=false,ready=()=>false,duration=()=>0}) {
    if(typing||!idle||clock<this.nextAt)return null;
    if(!this.welcomed){
      this.welcomed=true;
      this.nextAt=clock+duration('wave')+9000;
      return ready('wave')?'wave':null;
    }
    if(clock-this.lastActivity<9000)return null;
    const eligible=rotation.filter(item=>ready(item.id)&&(!bust||!item.fullBody));
    if(!eligible.length){this.nextAt=clock+9000;return null;}
    const chosen=eligible[this.cursor++%eligible.length].id;
    this.nextAt=clock+duration(chosen)+7000;
    return chosen;
  }
}
