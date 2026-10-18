try {
 const stored=JSON.parse(localStorage.getItem('schoolsafe-cursor-studio-school')||'{}');
 for(const key of ['name','motto','address','phone','email','website'])if(typeof stored[key]==='string')DB.settings.school[key]=stored[key];
} catch(_) {}
window._ssPreviewSid=DB.students[0]?.id;
render();
document.getElementById('data-file').addEventListener('change',async event=>{
 try{const file=event.target.files[0];if(file){SchoolSafeStudio.setData(JSON.parse(await file.text()));toast('Dossiers chargés dans le studio');}}
 catch(error){toast(error.message);}
});
document.getElementById('photo-file').addEventListener('change',event=>{
 const student=DB.students.find(s=>s.id===window._ssPreviewSid),file=event.target.files[0];
 if(!student||!file)return;
 if(!['image/png','image/jpeg','image/webp'].includes(file.type)||file.size>10*1024*1024)return toast('Photo PNG, JPEG ou WebP, maximum 10 Mo');
 const reader=new FileReader();reader.onload=()=>{student.photo=reader.result;ssRenderPreview();};reader.readAsDataURL(file);
});
document.getElementById('export-png').addEventListener('click',async()=>{
 const card=document.querySelector('#ss-card-studio .art');if(!card)return;
 try{
  const canvas=await html2canvas(card,{scale:2,useCORS:true,backgroundColor:'#ffffff',onclone:doc=>{doc.querySelectorAll('.ss-layout-handle').forEach(el=>el.remove());doc.querySelectorAll('.ss-layout-selected').forEach(el=>el.classList.remove('ss-layout-selected'));}});
  const link=document.createElement('a');link.download='SchoolSafe-recto.png';link.href=canvas.toDataURL('image/png');link.click();
 }catch(_){toast('Export PNG indisponible : vérifier les droits des images distantes');}
});
