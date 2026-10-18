(() => {
  const records = {};
  function add(id, location, source, note = '') {
    records[id] = { location, source, note, checkedOn: '2026-10-08' };
  }
  const ituri = 'https://whc.unesco.org/fr/list/718/';
  const virunga = 'https://virunga.org/wildlife/';
  ['rdc-okapi','rdc-paon-congo','rdc-elephant','rdc-bongo'].forEach(id => add(id,'Forêt de l’Ituri · RDC',ituri));
  add('rdc-bonobo','Parc national de la Salonga · RDC','https://whc.unesco.org/fr/list/280');
  add('rdc-gorille','Parc national des Virunga · RDC',virunga,'Gorille de montagne ; ne pas confondre avec le gorille de plaine.');
  add('rdc-hippopotame','Milieux aquatiques des Virunga · RDC',virunga);
  add('rdc-lion','Vallée de l’Ishasha · Virunga · RDC',virunga);
  add('rdc-chimpanze','Forêts des Virunga · RDC',virunga);
  add('rdc-leopard','Forêt de l’Ituri · RDC','https://programs.wcs.org/portals/42/publications/wcs_workingpaper_24_web.pdf','Présence documentée par des travaux scientifiques ; ne constitue pas un recensement actuel.');
  add('rdc-perroquet-gris','Forêts de Nkuba · Est de la RDC','https://onlinelibrary.wiley.com/doi/abs/10.1002/ece3.70039','Psittacus erithacus ; également présent dans d’autres pays africains.');
  add('rdc-crocodile','Plaine de la Ruzizi · RDC','https://www.iucncsg.org/365_docs/attachments/protarea/62884288e7f3dc56b3eccb943b8048a1.pdf');
  records['rdc-okapi'].group = 'Les Okapis';
  const mining = 'https://www.usgs.gov/centers/national-minerals-information-center/congo-kinshasa';
  ['min-diamant','min-cobalt','min-cassiterite','min-coltan','min-wolframite'].forEach(id => add(id,'Gisements documentés · RDC',mining,'Présence nationale documentée ; site précis à compléter. Cassitérite : minerai d’étain ; coltan : minerais de niobium et tantale ; wolframite : minerai de tungstène.'));
  add('min-or','Kibali · Haut-Uélé · RDC','https://www.barrick.com/English/operations/kibali/');
  records['min-or'].group = 'Les Ors';
  records['min-or'].background = 'min-or-context-v1.png';
  add('min-cuivre','Kamoa-Kakula · Lualaba · RDC','https://www.ivanhoemines.com/wp-content/uploads/IVN_AIF_31-Dec-2024-vF.pdf');
  add('min-malachite','Msesa · région du Katanga · RDC','https://www.africamuseum.be/de/research/collections_libraries/earth_sciences/collections/mineralogy/sample/7005','Localité du spécimen conservé au musée ; Katanga est ici une région historique.');
  add('min-tourmaline','Nord-Kivu · Sud-Kivu · RDC','https://ipisresearch.be/wp-content/uploads/2016/10/Mapping-minerals-in-eastern-DR-Congo_v005.pdf');
  add('min-amethyste','Occurrences répertoriées · RDC','https://www.mindat.org/locentries.php?m=198&p=14231','Répertoire minéralogique ; localité précise à compléter.');
  add('min-heterogenite','Lubumbashi · Haut-Katanga · RDC','https://www.mindat.org/show.php?clearcache=1&id=1885&ld=','Occurrence : mine de l’Étoile du Congo.');
  add('min-quartz','Manono-Kitotolo · Tanganyika · RDC','https://zh.mindat.org/locentry-648299.html');
  const adw = 'https://animaldiversity.org/accounts/';
  const aquatic = [
    ['orque','Océans du monde','Orcinus_orca','Espèce marine cosmopolite ; présence côtière en RDC non établie ici.'],
    ['narval','Arctique · Canada / Groenland','Monodon_monoceros'],
    ['beluga','Arctique et subarctique','Delphinapterus_leucas'],
    ['morse','Mers arctiques','Odobenus_rosmarus'],
    ['loutre','Pacifique Nord · États-Unis / Canada / Russie','Enhydra_lutris'],
    ['pieuvre','Pacifique Nord · Japon / États-Unis / Mexique','Enteroctopus_dofleini','Référence : pieuvre géante du Pacifique ; espèce de l’illustration à confirmer.'],
    ['crabe-royal','Pacifique Nord · Russie / États-Unis / Japon','Paralithodes_camtschaticus','Référence : crabe royal rouge ; espèce de l’illustration à confirmer.'],
    ['homard','Atlantique Nord-Ouest · Canada / États-Unis','Homarus_americanus','Référence : homard américain ; nom du catalogue générique, illustration à identifier.'],
    ['baleine-grise','Pacifique Nord · Mexique / États-Unis / Russie','Eschrichtius_robustus'],
    ['otarie','Pacifique Est · États-Unis / Mexique','Zalophus_californianus','Référence : otarie de Californie ; nom générique, illustration à identifier.'],
    ['phoque','Atlantique et Pacifique Nord','Phoca_vitulina','Référence : phoque commun ; nom générique, illustration à identifier.']
  ];
  aquatic.forEach(([id,place,taxon,note]) => add('aqua-'+id,place,adw+taxon+'/',note));
  add('aqua-saumon','Atlantique Nord · Canada / Europe','https://www.fisheries.noaa.gov/species/atlantic-salmon/science','Référence : saumon atlantique ; le catalogue ne précise pas l’espèce.');
  const land = [
    ['kangourou','Australie','Macropus_rufus','Référence : kangourou roux ; espèce de l’illustration à confirmer.'],
    ['koala','Australie','Phascolarctos_cinereus'],
    ['panda','Chine','Ailuropoda_melanoleuca'],
    ['ours-polaire','Arctique · Canada / Groenland / Russie','Ursus_maritimus'],
    ['jaguar','Amériques · notamment Brésil / Mexique','Panthera_onca'],
    ['puma','Amérique du Nord et du Sud','Puma_concolor'],
    ['bison','Amérique du Nord · Canada / États-Unis','Bison_bison','Référence : bison américain ; illustration à confirmer.'],
    ['grizzly','Amérique du Nord · Canada / États-Unis','Ursus_arctos','Grizzly : populations nord-américaines de l’ours brun.'],
    ['lama','Andes · Pérou / Bolivie','Lama_glama','Animal domestiqué ; région d’origine, pas pays exclusifs.'],
    ['alpaga','Andes · Pérou / Bolivie','Lama_pacos','Animal domestiqué ; également élevé ailleurs.'],
    ['paresseux','Amérique centrale et du Sud','Bradypus_variegatus','Référence : paresseux à gorge brune ; illustration à identifier.'],
    ['capybara','Amérique du Sud','Hydrochoerus_hydrochaeris']
  ];
  land.forEach(([id,place,taxon,note]) => add('terre-'+id,place,adw+taxon+'/',note));
  const birds = [
    ['toucan','Amérique du Sud · notamment Brésil','Ramphastos_toco','Référence : toucan toco ; illustration à identifier.'],
    ['ara-rouge','Amérique centrale et du Sud','Ara_macao'],
    ['kiwi','Nouvelle-Zélande','Apteryx_australis','Référence : kiwi austral ; illustration à identifier.'],
    ['cacatoes','Australie','Cacatua_galerita','Référence : cacatoès à huppe jaune ; illustration à identifier.'],
    ['manchot','Antarctique','Aptenodytes_forsteri','Référence : manchot empereur ; nom générique, illustration à identifier.'],
    ['aigle','Afrique subsaharienne · dont RDC','Haliaeetus_vocifer','Le pygargue vocifer est présent en RDC. Cela n’identifie pas l’aigle de l’illustration.'],
    ['colibri','Amérique du Nord et centrale','Archilochus_colubris','Référence : colibri à gorge rubis ; illustration à identifier.'],
    ['harfang','Régions arctiques · Canada / Russie','Nyctea_scandiaca'],
    ['macareux','Atlantique Nord · Islande / Canada / Royaume-Uni','Fratercula_arctica','Référence : macareux moine ; illustration à identifier.'],
    ['condor','Andes · Pérou / Chili / Argentine','Vultur_gryphus','Référence : condor des Andes ; illustration à identifier.'],
    ['cardinal','Amérique du Nord · États-Unis / Mexique','Cardinalis_cardinalis']
  ];
  birds.forEach(([id,place,taxon,note]) => add('ois-'+id,place,adw+taxon+'/',note));
  add('ois-oiseau-paradis','Nouvelle-Guinée · notamment Papouasie-Nouvelle-Guinée','https://australian.museum/about/history/exhibitions/birds-of-paradise/','Plusieurs espèces ; espèce de l’illustration à identifier.');
  const additions = [
    ['Sites naturels de la RDC','site-lukaya','Chutes de Lukaya','Environs de Kinshasa · RDC','https://www.bonobos.org/visit-lola-ya-bonobo/','Illustration générée évoquant les petites chutes de Lukaya, non photographie documentaire du site.'],
    ['Sites naturels de la RDC','site-embouchure-congo','Embouchure du fleuve Congo','Muanda · Kongo Central · RDC','https://www.ramsar.org/sites/default/files/documents/library/wurc_dr-congo_inventaire2008.pdf','Illustration générée de l’estuaire et des mangroves, non relevé géographique ni photographie du site.'],
    ['Faune et poissons de la RDC','rdc-rhinoceros','Rhinocéros blanc du Sud','Garamba · RDC · Réintroduit','https://www.africanparks.org/white-rhino-return-garamba-national-park','Ceratotherium simum simum : introduit depuis l’Afrique du Sud en 2023. Illustration générée ; ne pas confondre avec le rhinocéros blanc du Nord historique.'],
    ['Faune et poissons de la RDC','rdc-poisson-tigre','Poisson-tigre géant','Bassin du Congo · RDC','https://faunafri.africamuseum.be/cloffa/table/taxon2/view/?idtaxon%3Aint=1457','Hydrocynus goliath : poisson d’eau douce, également présent hors RDC. Illustration générée.'],
    ['Faune et poissons de la RDC','rdc-teleogramma','Teleogramma brichardi','Rapides du Bas-Congo · RDC','https://www.amnh.org/explore/videos/biodiversity/fish-speciation-congo-river','Cichlidé d’eau douce documenté dans le Congo inférieur. Illustration générée, anatomie à valider avant édition pédagogique définitive.'],
    ['Patrimoines du monde','terre-loup-gris','Loup gris','Canada · Europe · Asie','https://animaldiversity.org/accounts/Canis_lupus/','Canis lupus : présence en RDC non établie dans cette recherche. Classé dans le monde, pas parmi la faune congolaise. Illustration générée.']
  ];
  additions.forEach(([category,id,name,location,source,note]) => {
    add(id,location,source,note);
    records[id].illustrated = true;
    records[id].landscapeOnly = id.startsWith('site-');
    const catalogue = window._ssCARDS;
    if (!catalogue) return;
    const group = catalogue.PATG[category] || (catalogue.PATG[category] = []);
    if (!group.some(([value]) => value === id)) group.push([id,name]);
    if (!catalogue.PATS.some(item => item.v === id)) catalogue.PATS.push({v:id,n:name});
  });
  const royalHeritage = [
    ['kuba','Kuba · Ndop royal','Région du Kasaï · RDC','https://commons.wikimedia.org/wiki/File:Brooklyn_Museum_61.33_Ndop_Portrait_of_King_Mishe_miShyaang_maMbul.jpg','Ndop de Mishe miShyaang maMbul. Photographie : Brooklyn Museum, inventaire 61.33. CC BY 3.0. Sculpture royale idéalisée, non portrait photographique.','https://creativecommons.org/licenses/by/3.0/'],
    ['kongo','Kongo · Sculpture nkisi','Angola ou RDC','https://www.metmuseum.org/art/collection/search/316404','Nkisi, XIXe siècle. The Metropolitan Museum of Art, 1990.334. Image en domaine public. Objet rituel, non portrait royal.',''],
    ['luba','Luba / Hemba · Siège royal','Région de Kateba · RDC','https://www.metmuseum.org/art/collection/search/314034','Lupona, Ngongo ya Chintu, vers 1840–1870. Attribution Luba ou Hemba. The Metropolitan Museum of Art, 1979.290. Image en domaine public.',''],
    ['lunda','Lunda · Chibinda Ilunga','Tradition chokwe · Angola','https://commons.wikimedia.org/wiki/File:Chokwe_Figur_Chibinda_Ilunga_Museum_Rietberg_2007-1.jpg','Chibinda Ilunga, atelier chokwe, vers 1850, Museum Rietberg 2007.1. Photo Andreas Praefcke, domaine public. Référence liée aux traditions lunda, non portrait de toute la royauté lunda.',''],
    ['teke','Teke · Figure d’ancêtre','RDC · Patrimoine transfrontalier','https://www.metmuseum.org/art/collection/search/824207','Figure d’ancêtre, XIXe siècle. The Metropolitan Museum of Art, 2019.477.3. Image en domaine public. Non portrait du Makoko ; Mbé se trouve en République du Congo.','']
  ];
  royalHeritage.forEach(([key,name,location,source,note,license]) => {
    const id = 'pat-royaume-' + key;
    add(id,location,source,note);
    Object.assign(records[id], { image:id + '.jpg', museumObject:true, license, credit: key === 'kuba' ? 'Photo : Brooklyn Museum · CC BY 3.0' : key === 'lunda' ? 'Photo : Andreas Praefcke · Domaine public' : 'The Met · Domaine public' });
    const catalogue = window._ssCARDS;
    if (!catalogue) return;
    const group = catalogue.PATG['Patrimoines des royaumes'] || (catalogue.PATG['Patrimoines des royaumes'] = []);
    if (!group.some(([value]) => value === id)) group.push([id,name]);
    if (!catalogue.PATS.some(item => item.v === id)) catalogue.PATS.push({v:id,n:name});
  });
  window.SS_HERITAGE_ORIGINS = Object.freeze(Object.fromEntries(Object.entries(records).map(([id,record]) => [id,Object.freeze(record)])));
})();
