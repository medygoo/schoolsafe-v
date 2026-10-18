(function (root) {
  'use strict';
  function monthDays(year, month) {
    const first = new Date(year, month, 1, 12);
    const offset = (first.getDay() + 6) % 7;
    return Array.from({length:42}, (_, index) => {
      const date = new Date(year, month, 1 - offset + index, 12);
      return {day:date.getDate(), inMonth:date.getMonth()===month,
        iso:[date.getFullYear(),String(date.getMonth()+1).padStart(2,'0'),String(date.getDate()).padStart(2,'0')].join('-')};
    });
  }
  const fieldTypes = [
    ['select','Liste'],['autos','Liste automatique'],['exports','Liste export'],
    ['multiple','Choix multiples'],['text','Texte'],['textarea','Texte long'],
    ['radio','Case à cocher'],['numeric','Nombre'],['date','Date'],['files','Fichiers']
  ];
  const api = {monthDays,fieldTypes,hasOptions:type=>['select','autos','exports','multiple'].includes(type)};
  if(typeof module==='object' && module.exports)module.exports=api;
  else root.SchoolSafeDetailsModel=api;
})(typeof window==='undefined'?{}:window);
