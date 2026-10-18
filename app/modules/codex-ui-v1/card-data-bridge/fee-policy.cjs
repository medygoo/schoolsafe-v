'use strict';
function decimalCents(value){
 if(typeof value!=='string'||!/^\d{1,12}(?:\.\d{1,2})?$/.test(value))throw new Error('INVALID_AMOUNT');
 const [whole,fraction='']=value.split('.');return BigInt(whole)*100n+BigInt(fraction.padEnd(2,'0'));
}
function classifyFee({threshold,tolerancePercent,paid,hasData}){
 const minimum=decimalCents(threshold);
 if(minimum<=0n||!Number.isInteger(tolerancePercent)||tolerancePercent<0||tolerancePercent>100)throw new Error('INVALID_POLICY');
 if(!hasData)return {color:'yellow',label:'Orienter au bureau — données indisponibles',reason:'no_data'};
 let amount;try{amount=decimalCents(paid);}catch(_){return {color:'yellow',label:'Orienter au bureau — données à vérifier',reason:'invalid_data'};}
 if(amount>=minimum)return {color:'blue',label:'Barème respecté',reason:'threshold_met'};
 if(amount>0n&&amount*100n>=minimum*BigInt(100-tolerancePercent))return {color:'yellow',label:'Orienter au bureau',reason:'within_tolerance'};
 return {color:'red',label:'Validation du responsable requise',reason:'below_tolerance'};
}
module.exports={decimalCents,classifyFee};
