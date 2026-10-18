/* SchoolSafe menu. A main function opens its pages. A second press closes them. */
(function () {
	var companionParams = new URLSearchParams(location.search);
	if (companionParams.get('ss_companion') === '1' && companionParams.get('modname') === 'SchoolSafe_Jaspe/Assistant.php') return;
	var centralEntries = [];
	function hiddenMenuPath(path) {
		return path === 'Library/DocumentFields.php' || /^Messaging\//.test(path);
	}
	var backgroundChoices = ['white', 'blue', 'gray', 'mint', 'rose', 'yellow', 'night'];
	function syncJaspeTheme() {
		var frame = document.querySelector('#ss-jaspe-dialog iframe');
		var doc = frame && frame.contentDocument;
		if (doc) doc.documentElement.dataset.ssConversationTheme = document.documentElement.dataset.ssBackground;
	}
	try {
		var savedBackground = localStorage.getItem('schoolsafe-background');
		document.documentElement.dataset.ssBackground = backgroundChoices.includes(savedBackground) ? savedBackground : 'white';
	} catch (_) { document.documentElement.dataset.ssBackground = 'white'; }
	// Shared SchoolSafe presentation is loaded by Warehouse for every theme (2026-10-14).
	var centralProfile = null;
	var headerNotificationUrl = null;
	var branchDefinitions = [
		['users', 'Utilisateurs', 'Users'],
		['pilotage', 'Tableau de bord', 'School_Setup'], ['school', '\u00c9cole', 'School_Setup'],
		['pedagogy', 'P\u00e9dagogie', 'Grades'], ['security', 'S\u00e9curit\u00e9 et contr\u00f4le', 'Discipline'],
		['finance', 'Finance', 'Student_Billing'],
		['accounting', 'Comptabilit\u00e9', 'Accounting'], ['people', 'Personnel', 'Users'],
		['inventory', 'Stock / Inventaire', 'Resources'], ['communication', 'Communication', 'Resources'],
		['care', 'Vie et bien-\u00eatre', 'Food_Service'], ['administration', 'Administration', 'Users'],
		['reports', 'Contr\u00f4le et rapports', 'Grades']
	];
	function destination(path) {
		if (/^Users\//.test(path) && path !== 'Users/TeacherPrograms.php') return 'users';
		if (/^SchoolSafe\/Remediation/.test(path)) return 'pedagogy';
		if (/^SchoolSafe\/Notifications/.test(path)) return 'communication';
		if (/^SchoolSafe\/(Studio|IssueCard|PrintRequest|Family)/.test(path)) return 'school';
		if (/^SchoolSafe\/.*(Fee|Payment)/.test(path)) return 'finance';
		if (/^SchoolSafe\//.test(path)) return 'security';
		if (/^(Dashboards)\//.test(path)) return 'pilotage';
		if (/^(Audit|Email_Log)\//.test(path)) return 'security';
		if (/^(Backup|PDF_Archive|Semester_Rollover)\//.test(path)) return 'administration';
		if (/^(Certificate|Students_Import|Student_ID_Card)\//.test(path)) return 'school';
		if (/^(Class_Diary|Grades_Import|Lesson_Plan|Quiz|Timetable_Import)\//.test(path)) return 'pedagogy';
		if (/^(Attendance_Excel_Sheet|Entry_Exit|NFC_QR_Actions)\//.test(path)) return 'security';
		if (/^(Human_Resources|Staff_Absences|Staff_Parents_Import)\//.test(path)) return 'people';
		if (/^(Embedded_Resources|Library|School_Inventory)\//.test(path)) return 'inventory';
		if (/^(Email|Jitsi_Meet|Meeting|Messaging|SMS)\//.test(path)) return 'communication';
		if (/^(Hostel|Medical_Report)\//.test(path)) return 'care';
		if (/^Reports\//.test(path)) return 'reports';
		if (/^School_Setup\/(PortalNotes|PortalPolls)\.php$/.test(path) || path === 'Custom/NotifyParents.php') return 'communication';
		if (/^School_Setup\/(Configuration|SchoolFields|CopySchool|Rollover)\.php$/.test(path) || /^Users\/(Profiles|Exceptions|UserFields|Preferences)\.php$/.test(path)) return 'administration';
		if (path === 'School_Setup/AccessLog.php' || /^(Attendance|Discipline)\//.test(path)) return 'security';
		if (/^(Scheduling|Grades|Eligibility)\//.test(path) || path === 'Users/TeacherPrograms.php') return 'pedagogy';
		if (/^Accounting\//.test(path)) return 'accounting';
		if (/^Student_Billing\//.test(path)) return 'finance';
		if (/^Food_Service\//.test(path)) return 'care';
		if (/^Resources\//.test(path)) return 'inventory';
		if (/^Users\//.test(path)) return 'people';
		if (/^(Students|School_Setup|Custom)\//.test(path)) return 'school';
		return null;
	}
	function presentationGroup(branch, link) {
		var path = new URL(link.href, window.location.href).searchParams.get('modname') || '';
		if (branch === 'users') return /^Users\/(Profiles|Exceptions|UserFields)\.php$/.test(path) ? 'Profils et droits' : 'Comptes et associations';
		if (branch === 'school' && /^SchoolSafe\/Family/.test(path)) return 'Familles et accès';
		if (branch === 'school' && /^SchoolSafe\/(Studio|IssueCard|PrintRequest)/.test(path)) return 'Cartes et documents';
		var extensionGroups = {
			Dashboards:'Tableaux de bord', Audit:'Audit', Email_Log:'Historique des courriels',
			Backup:'Sauvegardes', PDF_Archive:'Archives PDF', Semester_Rollover:'Report semestriel',
			Certificate:'Certificats', Students_Import:'Import des eleves', Student_ID_Card:'Cartes natives Rosario',
			Class_Diary:'Journal de classe', Grades_Import:'Import des notes', Lesson_Plan:'Preparation des lecons',
			Quiz:'Questionnaires', Timetable_Import:'Import des horaires', Attendance_Excel_Sheet:'Export des presences',
			Entry_Exit:'Entrees et sorties', NFC_QR_Actions:'Actions NFC et QR', Human_Resources:'Qualifications',
			Staff_Absences:'Absences du personnel', Staff_Parents_Import:'Import du personnel et des parents',
			Embedded_Resources:'Ressources integrees', Library:'Bibliotheque', School_Inventory:'Inventaire',
			Email:'Courriels', Jitsi_Meet:'Visioconference', Meeting:'Reunions', Messaging:'Messagerie', SMS:'SMS',
			Hostel:'Internat', Medical_Report:'Dossier medical', Reports:'Rapports complementaires'
		};
		if (extensionGroups[path.split('/')[0]]) return extensionGroups[path.split('/')[0]];
		var groups = {
			pilotage: 'Vue ex\u00e9cutive', school: 'Scolarit\u00e9', pedagogy: 'Organisation p\u00e9dagogique',
			security: 'Action et contr\u00f4le', finance: 'Frais et caisse', accounting: '\u00c9critures',
			people: '\u00c9quipe', inventory: 'Stock', communication: '\u00c9changes', care: 'Suivi',
			administration: 'Configuration', reports: 'Rapports'
		};
		if (branch === 'school') {
			if (/^Students\/(StudentFields|EnrollmentCodes)\.php$/.test(path)) return 'R\u00e9f\u00e9rentiels \u00e9l\u00e8ves';
			if (/^Students\/AddUsers\.php$/.test(path) || /^Custom\/(CreateParents|RemoveAccess)\.php$/.test(path)) return 'Familles et acc\u00e8s';
			if (/^School_Setup\//.test(path)) return 'Organisation scolaire';
			return '\u00c9l\u00e8ves et inscriptions';
		}
		if (branch === 'pedagogy' && /^Grades\//.test(path)) return '\u00c9valuation';
		if (branch === 'pedagogy' && /^Eligibility\//.test(path)) return 'Accompagnement';
		if (branch === 'finance' && /^Student_Billing\/(StudentPayments|MassAssignPayments)\.php$/.test(path)) return 'Paiements et encaissements';
		if (branch === 'finance' && /^Student_Billing\/(StudentFees|MassAssignFees)\.php$/.test(path)) return 'Frais scolaires et prestations';
		if (branch === 'security' && path === 'School_Setup/AccessLog.php') return 'Historique';
		if (branch === 'security' && /AttendanceCodes|DisciplineForm/.test(path)) return 'Autorisation et suivi';
		if (branch === 'accounting' && /Categories/.test(path)) return 'R\u00e9f\u00e9rentiel';
		if (branch === 'communication' && /^School_Setup\//.test(path)) return 'Publication';
		if (branch === 'care' && /ServeMenus|Kiosk/.test(path)) return 'Action imm\u00e9diate';
		if (branch === 'care' && /DailyMenus|MenuItems|Menus/.test(path)) return 'Organisation';
		if (branch === 'care' && /Accounts|Statements|Transactions/.test(path)) return 'Finance cantine';
		if (branch === 'administration' && /Profiles|Exceptions|UserFields|CopySchool|Rollover/.test(path)) return 'Administration';
		return groups[branch] || 'Fonctions disponibles';
	}
	function organizeMenu() {
		var menu = document.querySelector('#menu .adminmenu');
		if (!menu || menu.getAttribute('data-ss-organized') === '1') return;
		var groups = {}, unknown = [];
		var originals = Array.from(menu.querySelectorAll(':scope > .menu-module'));
		originals.forEach(function (module) {
			if (module.querySelector('.menu-top')?.getAttribute('href') === 'Modules.php?modname=SchoolSafe_Jaspe/Assistant.php') return;
			var heading = module.querySelector('.menu-top').textContent.trim();
			var section = '';
			Array.from(module.querySelectorAll('.wp-submenu > li')).forEach(function (item) {
				if (item.classList.contains('menu-inter')) { section = item.textContent.trim(); return; }
				var link = item.querySelector('a[href]');
				if (!link) return;
				var path = new URL(link.href, window.location.href).searchParams.get('modname') || '';
				if (hiddenMenuPath(path)) { item.remove(); return; }
				var branch = /^(reports|rapports)$/i.test(section) ? 'reports' : destination(path);
				if (path === 'Users/TeacherPrograms.php') {
					if (!groups.users) groups.users = [];
					var shortcut = item.cloneNode(true);
					shortcut.querySelectorAll('[id]').forEach(function (node) { node.removeAttribute('id'); });
					groups.users.push({item:shortcut, heading:'Programmes enseignants'});
				}
				if (!branch) { unknown.push(module); return; }
				if (!groups[branch]) groups[branch] = [];
				groups[branch].push({item:item, heading:presentationGroup(branch, link)});
			});
		});
		var portal = document.querySelector('#menu-top > a[href]');
		if (portal && new URL(portal.href).searchParams.get('modname') === 'misc/Portal.php') {
			var home = document.createElement('li');
			var homeLink = document.createElement('a');
			homeLink.href = portal.getAttribute('href'); homeLink.textContent = 'Tableau de bord';
			if (new URL(window.location.href).searchParams.get('modname') === 'misc/Portal.php') homeLink.id = 'selectedMenuLink';
			home.appendChild(homeLink); groups.pilotage = [{item:home, heading:'Vue g\u00e9n\u00e9rale'}].concat(groups.pilotage || []);
		}
		centralEntries.forEach(function (entry) {
			var canonical = new URL(entry.url, location.href).href;
			var alreadyPresent = Object.keys(groups).some(function (branch) {
				return groups[branch].some(function (existing) {
					var anchor = existing.item.querySelector('a[href]');
					return anchor && new URL(anchor.getAttribute('href'), location.href).href === canonical;
				});
			});
			if (alreadyPresent) return;
			if (hiddenMenuPath(new URL(entry.url, location.href).searchParams.get('modname') || '')) return;
			var item = document.createElement('li');
			var link = document.createElement('a');
			link.href = entry.url; link.textContent = entry.title;
			item.appendChild(link);
			if (!groups[entry.branch]) groups[entry.branch] = [];
			groups[entry.branch].push({item:item, heading:entry.group});
		});
		branchDefinitions.forEach(function (definition) {
			var entries = groups[definition[0]];
			if (!entries || !entries.length) return;
			if (definition[0] === 'pilotage') {
				var dashboard = document.createElement('li');
				dashboard.className = 'menu-module ss-branch-pilotage';
				dashboard.dataset.ssBranch = 'pilotage';
				var direct = document.createElement('a');
				direct.className = 'menu-top'; direct.dataset.ssDirect = 'true';
				direct.href = 'Modules.php?modname=misc/Portal.php';
				direct.innerHTML = '<span class="module-icon School_Setup" aria-hidden="true"></span><span class="ss-menu-label">Tableau de bord</span>';
				dashboard.appendChild(direct); menu.prepend(dashboard);
				return;
			}
			var module = document.createElement('li');
			module.className = 'menu-module ss-branch-' + definition[0];
			module.setAttribute('data-ss-branch', definition[0]);
			var top = document.createElement('a');
			top.className = 'menu-top';
			top.href = entries[0].item.querySelector('a').getAttribute('href');
			var icon = document.createElement('span');
			icon.className = 'module-icon ' + definition[2];
			if (definition[0] === 'users') {
				icon.className = 'ss-users-icon';
				icon.textContent = '\uD83D\uDC65';
				icon.setAttribute('aria-hidden', 'true');
			}
			top.appendChild(icon);
			top.appendChild(document.createTextNode(' ' + definition[1]));
			var submenu = document.createElement('ul');
			submenu.className = 'wp-submenu';
			submenu.id = 'ss_menu_' + definition[0];
			// Group the original link nodes; never recreate their business destinations.
			var buckets = {};
			entries.forEach(function (entry) {
				if (!buckets[entry.heading]) buckets[entry.heading] = [];
				buckets[entry.heading].push(entry);
			});
			var order = Object.keys(buckets);
			if (definition[0] === 'school') {
				var schoolOrder = ['Élèves et inscriptions', 'Familles et accès', 'Cartes et documents', 'Cartes natives Rosario', 'Certificats', 'Organisation scolaire', 'Référentiels élèves', 'Import des eleves'];
				order.sort(function (a, b) {
					var ai = schoolOrder.indexOf(a), bi = schoolOrder.indexOf(b);
					return (ai < 0 ? schoolOrder.length : ai) - (bi < 0 ? schoolOrder.length : bi);
				});
				var schoolPaths = ['Students/Student.php', 'Custom/Registration.php', 'Students/AssignOtherInfo.php', 'Students/AddUsers.php', 'Custom/CreateParents.php', 'SchoolSafe/FamilyRead.php', 'SchoolSafe/FamilyRegister.php', 'SchoolSafe/FamilyApprove.php', 'SchoolSafe/FamilyReplace.php', 'Custom/RemoveAccess.php', 'SchoolSafe/Studio.php', 'SchoolSafe/IssueCard.php', 'SchoolSafe/PrintRequest.php', 'School_Setup/Schools.php', 'School_Setup/GradeLevels.php', 'School_Setup/Periods.php', 'School_Setup/MarkingPeriods.php', 'School_Setup/Calendar.php', 'Students/EnrollmentCodes.php', 'Students/StudentFields.php'];
				Object.keys(buckets).forEach(function (heading) {
					buckets[heading].sort(function (a, b) {
						function rank(entry) { var i = schoolPaths.indexOf(new URL(entry.item.querySelector('a').href).searchParams.get('modname')); return i < 0 ? schoolPaths.length : i; }
						return rank(a) - rank(b);
					});
				});
			}
			entries = order.flatMap(function (heading) { return buckets[heading]; });
			var previous = '';
			entries.forEach(function (entry) {
				if (entry.heading !== previous) {
					var label = document.createElement('li');
					label.className = 'menu-inter'; label.textContent = entry.heading;
					submenu.appendChild(label); previous = entry.heading;
				}
				submenu.appendChild(entry.item);
			});
			module.appendChild(top); module.appendChild(submenu); menu.appendChild(module);
			if (submenu.querySelector('#selectedMenuLink')) top.id = 'selectedModuleLink';
		});
		originals.forEach(function (module) {
			if (unknown.indexOf(module) < 0) module.remove();
		});
		menu.setAttribute('data-ss-organized', '1');
	}
	function markOpen() {
		var modules = document.querySelectorAll('.menu-module');
		var i;

		for (i = 0; i < modules.length; i++) {
			modules[i].classList.remove('ss-open');
			var top = modules[i].querySelector('.menu-top');
			if (top) top.setAttribute('aria-expanded', 'false');
		}

	}

	function bind() {
		organizeMenu();
		decorateSubmenus();
		prepareSettings();
		var brand = document.querySelector('#menu-top > a[href]');
		if (brand) {
			var brandImage = brand.querySelector('img');
			if (brandImage) {
				brandImage.src = '/schoolsafe-card-bridge/studio-validated-20261008/logo-schoolsafe.png';
				brandImage.alt = 'SchoolSafe';
				brandImage.classList.add('ss-menu-animated-logo');
				if (!brandImage.parentElement.classList.contains('ss-menu-emblem')) {
					var emblem = document.createElement('span'); emblem.className = 'ss-menu-emblem';
					brandImage.replaceWith(emblem); emblem.appendChild(brandImage);
				}
			}
		}
		if (brand && !brand.querySelector('.ss-brand-name')) {
			var name = document.createElement('strong'); name.className = 'ss-brand-name';
			name.textContent = 'SchoolSafe'; brand.appendChild(name);
		}
		if (brand) {
			var brandName = brand.querySelector('.ss-brand-name');
			if (brandName && !brandName.querySelector('.ss-brand-safe')) {
				brandName.textContent = 'School';
				var safe = document.createElement('span'); safe.className = 'ss-brand-safe';
				safe.textContent = 'Safe'; brandName.appendChild(safe);
			}
		}
		if (window.jQuery && window.submenuOnTouch) {
			window.jQuery('.adminmenu .menu-top').off('click touch', window.submenuOnTouch);
			window.jQuery('.adminmenu .menu-top').off('mouseover focus');
		}

		var tops = document.querySelectorAll('.adminmenu .menu-top');
		tops.forEach(function (top) {
			var title = top.textContent.trim();
			top.setAttribute('aria-label', title); top.setAttribute('title', title);
			if (!top.querySelector('.ss-menu-label')) {
				var label = document.createElement('span'); label.className = 'ss-menu-label';
				Array.from(top.childNodes).filter(function (node) { return node.nodeType === 3; }).forEach(function (node) { label.appendChild(node); });
				top.appendChild(label);
			}
		});
		var i;

		for (i = 0; i < tops.length; i++) {
			if (tops[i].dataset.ssDirect === 'true') continue;
			var submenu = tops[i].parentElement.querySelector('.wp-submenu');
			if (submenu) {
				submenu.setAttribute('data-ss-title', tops[i].textContent.trim());
				submenu.setAttribute('aria-label', tops[i].textContent.trim());
				if (submenu.id) tops[i].setAttribute('aria-controls', submenu.id);
			}
			tops[i].setAttribute('aria-expanded', 'false');

			if (tops[i].getAttribute('data-ss-menu') === '1') {
				continue;
			}

			tops[i].setAttribute('data-ss-menu', '1');
			tops[i].addEventListener('click', onToggle, true);
		}

		markOpen();
	}
	function presentNativePage() {
		var body = document.getElementById('body');
		if (body) {
			var path = new URL(window.location.href).searchParams.get('modname') || '';
			body.setAttribute('data-ss-page', path.replace(/\.php$/, ''));
			var school = destination(path) === 'school' &&
				!/^Students\/(AdvancedReport|AddDrop|StudentBreakdown|Letters|StudentLabels|PrintStudentInfo)\.php$/.test(path) &&
				!/^Custom\/(MyReport|AttendanceSummary|AttendanceChart|AttendanceSummaryChart)\.php$/.test(path);
			var report = Array.from(document.querySelectorAll('#ss_menu_reports a[href]')).some(function (link) {
				return new URL(link.href, location.href).searchParams.get('modname') === path;
			});
			school = school && !report;
			var pedagogy = destination(path) === 'pedagogy' && !report;
			var security = destination(path) === 'security' && !report;
			var finance = destination(path) === 'finance' && !report;
			var extra = report ? 'reports' : ['accounting', 'people', 'inventory', 'communication', 'care', 'administration', 'reports'].includes(destination(path)) ? destination(path) : '';
			if (!report && destination(path) === 'users') extra = /^Users\/(Profiles|Exceptions|UserFields|Preferences)\.php$/.test(path) ? 'administration' : 'people';
			body.toggleAttribute('data-ss-users', destination(path) === 'users');
			body.setAttribute('data-ss-section', school ? 'school' : pedagogy ? 'pedagogy' : security ? 'security' : finance ? 'finance' : extra);
			if (school || pedagogy || security || finance || extra) {
				presentSchoolContent(body, pedagogy ? 'pedagogy' : security ? 'security' : finance ? 'finance' : extra || 'school');
				presentPageHeading(body);
				presentFormLayout(body);
			}
		}
		markOpen();
		if (menuIsNarrow()) setMobilePanel('');
	}
	function presentPageHeading(body) {
		if (body.querySelector('.ss-page-heading')) return;
		var title = body.querySelector('header[class*="ss-f"] h2, .header1 h2');
		if (!title) return;
		var heading = document.createElement('header');
		heading.className = 'ss-page-heading';
		var logo = document.createElement('img');
		logo.src = '/schoolsafe-card-bridge/studio-validated-20261008/logo-schoolsafe.png';
		logo.alt = 'SchoolSafe'; logo.width = 40; logo.height = 40;
		var label = document.createElement('h2'); label.textContent = title.textContent.trim();
		var back = document.createElement('button');
		back.type = 'button';
		back.className = 'ss-page-back';
		back.title = 'Retour au sous-menu'; back.setAttribute('aria-label', back.title);
		back.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopPropagation();
			var branch = document.querySelector('#selectedMenuLink, #selectedModuleLink')?.closest('.menu-module')?.dataset.ssBranch || destination(new URL(location.href).searchParams.get('modname') || '');
			var module = document.querySelector('.menu-module[data-ss-branch="' + branch + '"]');
			if (!module) return;
			setSettings(false);
			document.querySelectorAll('.menu-module').forEach(function (item) {
				item.classList.toggle('ss-open', item === module);
				item.querySelector('.menu-top')?.setAttribute('aria-expanded', String(item === module));
			});
			if (menuIsNarrow()) setMobilePanel('context');
			module.querySelector('.ss-context-close, .menu-top')?.focus();
		});
		var icon = document.createElement('img');
		icon.src = '/assets/themes/SchoolSafeCodex/navigation-icons/ArrowLeft.svg';
		icon.alt = ''; icon.width = 20; icon.height = 20;
		back.appendChild(icon); heading.append(logo, label, back);
		body.prepend(heading);
		body.querySelectorAll('.header1 h2, header[class*="ss-f"] h2').forEach(function (nativeTitle) {
			nativeTitle.classList.add('ss-native-title');
		});
		body.querySelectorAll('header[class*="ss-f"]').forEach(function (nativeHeader) {
			nativeHeader.classList.add('ss-native-heading');
		});
	}
	function presentFormLayout(body) {
		body.querySelectorAll('span, small, .badge, .label').forEach(function (badge) {
			if (!badge.children.length && !badge.closest('button, a, label, option, summary') && /^non[\s-]*premium$/i.test(badge.textContent.trim())) badge.hidden = true;
		});
		var pageHelp = {
			'Staff_Absences/AddAbsence': 'Vérifiez le membre du personnel concerné, les dates et les demi-journées de début et de fin. Complétez le type d’absence et les champs disponibles avant d’enregistrer. Les classes annulées sont distinctes de la durée de l’absence.',
			'Staff_Absences/Absences': 'Cette liste présente les absences accessibles à votre profil. Les dates et demi-journées définissent la période concernée ; ouvrez un dossier pour consulter ses informations et les actions autorisées.',
			'Staff_Absences/CancelledClasses': 'Cette rubrique concerne les cours annulés liés aux absences du personnel. Elle ne remplace pas le relevé des présences des élèves.',
			'Staff_Absences/AbsenceBreakdown': 'La répartition présente les jours d’absence selon les critères choisis. Vérifiez la période et le périmètre du rapport avant de comparer les résultats.',
			'Staff_Absences/AbsenceFields': 'Ces paramètres définissent les renseignements proposés dans les dossiers d’absence. Modifier un champ change le formulaire ; cela n’enregistre pas une nouvelle absence.',
			'Student_Billing/MassAssignFees': 'Le montant indiqué est attribué à chacun des élèves cochés, et non partagé entre eux. Vérifiez le titre, le montant et l’échéance avant de valider. Attribuer des frais crée une somme à régler ; cela n’enregistre pas un paiement.',
			'Student_Billing/MassAssignPayments': 'Cette opération enregistre le même montant de paiement pour chacun des élèves cochés. Vérifiez les élèves, le montant et les références avant de valider ; il ne s’agit pas d’un simple contrôle de solde.',
			'Student_Billing/StudentFees': 'Les frais correspondent aux sommes attribuées au dossier de l’élève. Leur création est distincte de l’enregistrement des paiements et du montant restant à régler.',
			'Student_Billing/StudentPayments': 'Cette rubrique enregistre les paiements du dossier élève. Vérifiez le montant et les références du règlement ; le solde dépend des frais et des paiements déjà enregistrés.',
			'Reports/Calculations': 'Les calculs combinent les champs élèves, les constantes et les fonctions disponibles. Le résultat dépend du périmètre scolaire et des données accessibles à votre profil. Enregistrer conserve la formule ; cela ne modifie pas les dossiers élèves.',
			'Reports/CalculationsReports': 'Cette rubrique présente les résultats des calculs enregistrés, selon la répartition et les données accessibles. Une valeur manquante ne signifie pas nécessairement zéro.',
			'Reports/SavedReports': 'Les rapports sauvegardés conservent une sélection de critères. Leur ouverture consulte les données actuelles accessibles à votre profil ; ce ne sont pas des copies figées des dossiers.',
			'SchoolSafe/RemediationRead': 'Consultez les dossiers de rattrapage et leur état pour la période concernée. La consultation ne valide pas une prise en charge.',
			'SchoolSafe/RemediationMap': 'Associez les types d’évaluation aux règles du rattrapage avant d’utiliser les résultats pour le suivi.',
			'SchoolSafe/RemediationDecide': 'Vérifiez le dossier et les résultats concernés avant de valider ou de refuser le rattrapage.',
			'SchoolSafe/RemediationSchedule': 'Programmez la séance du dossier concerné en vérifiant sa date et les informations demandées.',
			'SchoolSafe/RemediationFollowup': 'Enregistrez le suivi de la séance concernée. Ce suivi est distinct de la décision de réévaluation.',
			'SchoolSafe/RemediationReassess': 'Examinez le suivi du dossier avant de décider de sa réévaluation.',
			'SchoolSafe/RemediationBilling': 'Définissez la tarification du rattrapage. Une règle de tarif ne constitue pas un paiement enregistré.',
			'SchoolSafe/RemediationPaymentsRead': 'Consultez les paiements rattachés aux dossiers de rattrapage et leur état.',
			'SchoolSafe/RemediationPaymentsRecord': 'Enregistrez le paiement du dossier concerné après vérification du montant et des références.',
			'SchoolSafe/RemediationPaymentsManage': 'Vérifiez les références et le montant avant de confirmer et de répartir le paiement de rattrapage.',
			'Lesson_Plan/LessonPlans': 'Consultez les préparations de leçons disponibles pour les cours concernés.',
			'Class_Diary/Diaries': 'Le journal de classe rassemble les informations consignées pour les cours et les journées concernés.',
			'Timetable_Import/TimetableImport': 'L’import associe les colonnes du fichier aux données de l’emploi du temps. Vérifiez les correspondances avant de lancer l’import.',
			'Quiz/Quizzes': 'Cette page regroupe les questionnaires disponibles. Les questions se gèrent dans la rubrique Questions.',
			'Quiz/Questions': 'Préparez les questions et les réponses des questionnaires selon les options disponibles.',
			'Quiz/AnswerBreakdown': 'La répartition des réponses dépend des fonctions disponibles dans la version installée du module Quiz.',
			'Quiz/Configuration': 'Les paramètres disponibles dépendent de la version installée du module Quiz.',
			'Staff_Parents_Import/StaffParentsImport': 'Importez les comptes du personnel et des parents depuis un fichier. Vérifiez les colonnes, les profils et les associations avant de lancer l’import.',
			'Medical_Report/MedicalReport': 'Choisissez le rapport des visites à l’infirmerie ou celui des vaccinations et examens médicaux. Les données affichées restent soumises à vos droits.',
			'Users/User': 'Le dossier utilisateur regroupe l’identité, la photo et les informations du compte. Le profil définit le rôle ; les droits d’accès déterminent les fonctions autorisées.',
			'Users/AddStudents': 'Rattachez les élèves au parent sélectionné. Ces associations déterminent les enfants accessibles à ce parent ; vérifiez chaque rattachement avant d’enregistrer.',
			'Users/Profiles': 'Les profils regroupent les autorisations communes à un rôle. Modifier un profil peut changer les accès de tous les utilisateurs qui lui sont associés.',
			'Users/Exceptions': 'Les droits individuels complètent les autorisations du profil. Vérifiez l’utilisateur concerné et les permissions choisies avant d’enregistrer.',
			'Users/UserFields': 'Ces champs structurent les informations complémentaires des dossiers utilisateurs. Ils ne créent pas de compte et ne donnent pas de droits d’accès.',
			'Users/Preferences': 'Les préférences personnalisent votre compte et les options disponibles pour votre profil.',
			'Users/TeacherPrograms': 'Choisissez l’enseignant et le cours concernés pour accéder au programme demandé. Les actions disponibles restent limitées par vos droits Rosario.',
			'School_Setup/Schools': 'Cette page regroupe les informations de l’établissement. Vérifiez l’école sélectionnée avant de modifier son nom, ses coordonnées ou les autres renseignements.',
			'School_Setup/Periods': 'Les tranches horaires découpent la journée scolaire. Elles servent à situer les cours et, selon les réglages, à suivre la présence par cours. Vérifiez les horaires et leur ordre avant d’enregistrer.',
			'Students_Import/StudentsImport': 'L’import crée des dossiers élèves à partir d’un fichier. Préparez le fichier, associez chaque colonne au bon champ Rosario et vérifiez les correspondances avant de lancer l’import.',
			'Students/AddUsers': 'Associez les parents aux élèves concernés. Ce rattachement détermine quels enfants un parent peut consulter : vérifiez chaque association avant de valider.',
			'Custom/CreateParents': 'Cette action crée des comptes parents à partir du champ courriel choisi dans les dossiers élèves, puis rattache les parents aux élèves. Vérifiez le champ, la sélection et les adresses avant de lancer la création.',
			'Custom/RemoveAccess': 'Cette page permet de bloquer ou de rétablir l’accès des élèves sélectionnés et des parents associés. Vérifiez le mode de l’action et la sélection avant de valider ; ce n’est pas une simple consultation.',
			'SchoolSafe/FamilyRead': 'Consultez les personnes associées à la récupération de l’élève. Leur présence dans la liste ne remplace pas la vérification de leur autorisation.',
			'SchoolSafe/FamilyRegister': 'Enregistrez une personne familiale ou une demande d’accréditation pour l’élève concerné. Vérifiez son identité et le lien avec l’élève ; l’enregistrement et la validation sont des étapes distinctes.',
			'SchoolSafe/FamilyApprove': 'Cette page sert à valider les personnes, accréditations ou photos selon vos droits. Vérifiez l’élève et la personne concernés avant toute décision.',
			'SchoolSafe/FamilyReplace': 'Retirez ou remplacez une personne associée à un élève à partir de la demande reçue à l’école. Vérifiez les identifiants et la personne concernée avant de valider.',
			'SchoolSafe/Studio': 'Préparez la présentation des cartes dans le studio. La conception du modèle, l’émission d’une carte officielle et sa demande d’impression sont des étapes distinctes.',
			'Student_ID_Card/StudentIDCard': 'Recherchez et sélectionnez les élèves pour préparer leurs cartes avec le module natif Rosario. Vérifiez les élèves et les options de présentation avant la génération.',
			'SchoolSafe/PrintRequest': 'Cette action enregistre une demande d’impression de carte pour l’élève indiqué. Vérifiez son identifiant Rosario et choisissez le format avant de valider. Une demande enregistrée ne signifie pas que la carte a déjà été imprimée.',
			'SchoolSafe/IssueCard': 'Cette page sert à émettre une carte officielle pour un élève. Vérifiez l’élève concerné avant de valider ; l’émission et la demande d’impression sont deux opérations distinctes.',
			'School_Setup/Calendar': 'Le calendrier indique les jours scolaires et les événements. Vérifiez le calendrier et le mois sélectionnés avant de consulter une date. La création ou la modification d’un calendrier dépend de vos droits.',
			'Students/AssignOtherInfo': 'Cette page applique les mêmes informations à plusieurs élèves. Recherchez d’abord les élèves, vérifiez votre sélection, puis renseignez les informations à attribuer avant de valider.',
			'Students/Student': 'Recherchez d’abord un élève pour ouvrir son dossier. Les onglets regroupent ses différentes informations ; les modifications possibles dépendent de vos droits.',
			'Certificate/CertificateEnrollment': 'Recherchez et sélectionnez les élèves concernés pour préparer leurs certificats de scolarité. La recherche avancée permet de préciser les critères de sélection.',
			'Email_Log/EmailLog': 'Ce journal permet de consulter les emails enregistrés par Rosario et les erreurs signalées. Filtrez les dates pour retrouver un envoi. Une entrée du journal ne garantit pas que le destinataire a reçu ou lu le message.',
			'School_Setup/MarkingPeriods': 'Les périodes scolaires organisent l’année, les semestres et les périodes de notation. Sélectionnez le niveau concerné avant de modifier ses dates et ses paramètres ; ces réglages servent aux notes et aux bulletins.'
		};
		if (body.dataset.ssPage === 'Email_Log/EmailLog' && new URL(location.href).searchParams.get('tab') === 'config') {
			pageHelp['Email_Log/EmailLog'] = 'Définissez la durée de conservation du journal en jours. Les entrées plus anciennes sont supprimées automatiquement lors de la consultation du journal. L’option de notifications inclut les emails automatiques de Rosario dans ce journal ; elle ne déclenche pas leur envoi.';
			body.querySelectorAll('form > .postbox-wrapper').forEach(function (panel) { panel.classList.add('ss-help-settings'); });
		}
		var heading = body.querySelector('.ss-page-heading');
		if (body.dataset.ssSection && !pageHelp[body.dataset.ssPage]) {
			var nativeHelp = Array.from(body.querySelectorAll('header[class*="ss-f"] > p')).find(function (paragraph) { return !/kicker/.test(paragraph.className); });
			if (nativeHelp && nativeHelp.textContent.trim()) pageHelp[body.dataset.ssPage] = nativeHelp.textContent.trim();
		}
		if (heading && pageHelp[body.dataset.ssPage] && !body.querySelector('.ss-page-explanation')) {
			var description = document.createElement('p');
			description.className = 'ss-page-explanation';
			description.textContent = pageHelp[body.dataset.ssPage];
			heading.insertAdjacentElement('afterend', description);
		}
		if (heading && body.querySelector('.ss-page-explanation') && pageHelp[body.dataset.ssPage]) body.querySelector('.ss-page-explanation').textContent = pageHelp[body.dataset.ssPage];
		var search = body.querySelector('#search');
		if (search && search.querySelector('details')) {
			search.classList.add('ss-advanced-search');
			search.closest('table')?.classList.add('ss-search-shell');
			search.closest('.postbox-wrapper')?.classList.add('ss-search-wrapper');
			search.querySelectorAll('details').forEach(function (group) {
				var summary = group.querySelector('summary');
				var explanations = {
					'Inscription': 'Recherchez les élèves selon leur calendrier scolaire, leurs dates de présence et leur inscription pour l’année prochaine.',
					'Planification': 'Recherchez les élèves inscrits à un cours ou ayant demandé un cours. Le lien Choisir permet de sélectionner le cours concerné.',
					'Présence': 'Recherchez les élèves selon leur nombre d’absences, sur l’année, le semestre ou la période. Pour les absences d’une classe, choisissez d’abord le cours dans Planification.',
					'Notes': 'Recherchez les élèves selon leur moyenne, leur classement ou les notes obtenues pendant la période sélectionnée. Les champs Entre permettent de fixer une valeur minimale et maximale.',
					'Éligibilité': 'Recherchez les élèves selon leur éligibilité aux activités scolaires ou selon une activité précise.',
					'Cantine': 'Recherchez les élèves à partir des critères de cantine disponibles, notamment leur compte de restauration.',
					'Discipline': 'Recherchez les élèves concernés par un incident disciplinaire, selon le rapporteur, la date et les informations renseignées dans le rapport.',
					'Facturation élèves': 'Recherchez les élèves selon les critères de frais scolaires et de solde disponibles. Cette rubrique filtre les élèves ; elle n’enregistre pas de paiement.',
					'General Info': 'Recherchez les élèves selon les informations générales de leur dossier.',
					'Medical': 'Recherchez les élèves selon les champs médicaux disponibles dans leur dossier, dans les limites de vos droits.'
				};
				var explanation = summary && explanations[summary.textContent.trim()];
				if (explanation && !group.querySelector('.ss-widget-explanation')) {
					var help = document.createElement('p');
					help.className = 'ss-widget-explanation';
					help.textContent = explanation;
					summary.insertAdjacentElement('afterend', help);
				}
				group.querySelectorAll('table.widefat').forEach(function (table) {
					if (table.closest('details') !== group || table.parentElement.closest('table.widefat')) return;
					table.classList.add('ss-search-criteria');
					Array.from(table.rows).forEach(function (row) {
						if (row.cells.length === 2 && row.cells[1].querySelectorAll('input[type="number"]').length === 2) row.cells[1].classList.add('ss-search-range');
					});
				});
			});
		}
		if (body.dataset.ssPage === 'School_Setup/Schools') {
			body.querySelectorAll('table').forEach(function (table) {
				if (table.querySelector('table') || table.classList.contains('widefat') || table.rows.length < 4 || !table.querySelector('input, .legend-gray')) return;
				table.classList.add('ss-school-details');
				Array.from(table.rows).forEach(function (row, index) {
					if (row.cells.length > 1) row.classList.add('ss-school-location-row');
					if (index < 3 || row.cells.length > 1) row.classList.add('ss-form-span');
				});
			});
		}
		body.querySelectorAll('fieldset > table, #general_table').forEach(function (table) {
			if (table.closest('.widefat, .list-wrapper') || table.querySelector('table, th')) return;
			var rows = Array.from(table.rows);
			if (rows.length < 2 || !rows.every(function (row) {
				return row.cells.length <= 2 && Array.from(row.cells).every(function (cell) { return cell.colSpan === 1; });
			}) || !table.querySelector('input, select, textarea')) return;
			table.classList.add('ss-form-grid');
			rows.forEach(function (row) {
				if (row.cells.length === 2) row.classList.add('ss-form-pair');
				if (row.querySelector('textarea') || row.querySelectorAll('select').length > 2) row.classList.add('ss-form-span');
			});
		});
		if (body.dataset.ssPage === 'Reports/Calculations') {
			body.querySelectorAll('.reports-calculation-item').forEach(function (item) {
				var name = item.textContent.trim();
				if (name) { item.title = 'Insérer : ' + name; item.setAttribute('aria-label', item.title); }
			});
			body.querySelectorAll('a[class^="reports-calculation-"] img[title]').forEach(function (image) {
				image.parentElement.title = image.title;
				image.parentElement.setAttribute('aria-label', image.title);
			});
		}
		if (/^Student_Billing\/MassAssign(Fees|Payments)$/.test(body.dataset.ssPage || '')) {
			var amount = body.querySelector('input[name="amount"]');
			var fields = amount && amount.closest('table');
			if (fields && !fields.querySelector('table')) {
				fields.classList.add('ss-billing-assignment-fields');
				Array.from(fields.rows).forEach(function (row) {
					if (row.querySelector('select,input[name="comments"]')) row.classList.add('ss-billing-full-row');
				});
			}
		}
	}
	function presentSchoolContent(body, section) {
		section = section || 'school';
		body.querySelectorAll('table.widefat, table.calendar').forEach(function (table) {
			if (table.closest('.list-wrapper, .ss-school-table-scroll')) return;
			var wrapper = document.createElement('div');
			wrapper.className = 'ss-school-table-scroll';
			wrapper.tabIndex = 0;
			wrapper.setAttribute('role', 'region');
			wrapper.setAttribute('aria-label', body.querySelector('h2')?.textContent.trim() || 'Liste');
			table.parentNode.insertBefore(wrapper, table);
			wrapper.appendChild(table);
		});
		body.querySelectorAll('.list-wrapper').forEach(function (wrapper) {
			wrapper.tabIndex = 0;
			wrapper.setAttribute('role', 'region');
			wrapper.setAttribute('aria-label', body.querySelector('h2')?.textContent.trim() || 'Liste');
		});
		body.querySelectorAll('iframe.schoolsafe-native-tool').forEach(function (frame) {
			function themeTool() {
				if (new URL(frame.src, location.href).origin !== location.origin) return;
				var doc = frame.contentDocument;
				if (!doc?.body || doc.querySelector('[data-ss-school-style]')) return;
				var link = doc.createElement('link');
				link.rel = 'stylesheet';
				link.href = new URL('assets/themes/SchoolSafeCodex/' + section + '-impeccable.css?v=1', location.href).href;
				link.dataset.ssSchoolStyle = '1';
				doc.body.classList.add('ss-school-tool');
				doc.body.classList.add('ss-tool-embedded');
				doc.body.dataset.ssToolSection = section;
				doc.head.appendChild(link);
				var shared = doc.createElement('link'); shared.rel = 'stylesheet';
				shared.href = new URL('assets/themes/SchoolSafeCodex/branch-page-heading.css?v=1', location.href).href;
				doc.head.appendChild(shared);
				var details = doc.createElement('link'); details.rel = 'stylesheet';
				details.href = new URL('assets/themes/SchoolSafeCodex/secondary-surfaces.css?v=1', location.href).href;
				doc.head.appendChild(details);
				if (section === 'school' || section === 'pedagogy' || section === 'security' || section === 'finance' || section === 'accounting' || section === 'people' || section === 'inventory' || section === 'communication' || section === 'care' || section === 'administration' || section === 'reports') {
					presentFormLayout(doc.body);
					var layout = doc.createElement('link'); layout.rel = 'stylesheet';
					layout.href = new URL('assets/themes/SchoolSafeCodex/branch-form-layout.css?v=1', location.href).href;
					doc.head.appendChild(layout);
				}
			}
			if (!frame.dataset.ssSchoolTheme) {
				frame.dataset.ssSchoolTheme = '1';
				frame.addEventListener('load', themeTool);
			}
			themeTool();
		});
	}

	function onToggle(e) {
		if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) {
			return;
		}

		e.preventDefault();
		e.stopImmediatePropagation();
		setSettings(false);

		var link = e.currentTarget;
		var module = link.parentElement;
		var wasOpen = module.classList.contains('ss-open');
		var tops = document.querySelectorAll('.adminmenu .menu-top');
		var modules = document.querySelectorAll('.menu-module.ss-open');
		var i;

		for (i = 0; i < tops.length; i++) {
			if (tops[i].id === 'selectedModuleLink') {
				tops[i].removeAttribute('id');
			}

			tops[i].setAttribute('aria-expanded', 'false');
		}

		for (i = 0; i < modules.length; i++) {
			modules[i].classList.remove('ss-open');
		}

		if (!wasOpen) {
			link.setAttribute('aria-expanded', 'true');
			module.classList.add('ss-open');
			if (menuIsNarrow()) {
				setMobilePanel('context');
				module.querySelector('.ss-context-close').focus();
			}
		} else if (menuIsNarrow()) setMobilePanel('primary');
	}

	function menuIsNarrow() {
		return window.matchMedia('(max-width: 700px)').matches;
	}
	function setSettings(open) {
		if (open) markOpen();
		document.body.classList.toggle('ss-settings-open', open);
		var button = document.getElementById('ss-settings-button');
		if (button) button.setAttribute('aria-expanded', String(open));
		if (open && menuIsNarrow()) setMobilePanel('primary');
		if (open) document.querySelector('#ss-native-settings .ss-settings-close')?.focus();
	}
	function prepareSettings() {
		var menu = document.getElementById('menu'), form = document.querySelector('#menu-top > form');
		if (!menu || !form) return;
		form.id = 'ss-native-settings';
		var workingStudent = menu.querySelector('#menu-top > .current-person.student');
		if (workingStudent) form.appendChild(workingStudent);
		if (centralProfile && !form.querySelector('.ss-profile-section')) {
			var section = document.createElement('section'); section.className = 'ss-profile-section';
			section.setAttribute('aria-label', 'Mon profil');
			var identity = document.createElement('div'); identity.className = 'ss-profile-identity';
			var avatar = document.createElement('span'); avatar.className = 'ss-profile-avatar'; avatar.setAttribute('aria-hidden', 'true');
			avatar.textContent = centralProfile.name.split(/\s+/).filter(Boolean).slice(0, 2).map(function (part) { return part[0]; }).join('').toUpperCase();
			if (typeof centralProfile.photo === 'string' && centralProfile.photo && !/^(?:[a-z]+:|\/\/)/i.test(centralProfile.photo)) {
				var photo = document.createElement('img'); photo.src = centralProfile.photo; photo.alt = '';
				photo.addEventListener('error', function () { avatar.textContent = centralProfile.name.split(/\s+/).filter(Boolean).slice(0, 2).map(function (part) { return part[0]; }).join('').toUpperCase(); });
				avatar.replaceChildren(photo);
			}
			var name = document.createElement('strong'); name.textContent = centralProfile.name || 'Mon profil';
			identity.append(avatar, name); section.appendChild(identity);
			['Nom et photo de profil', 'Changer mon mot de passe'].forEach(function (label) {
				var link = document.createElement('a'); link.href = centralProfile.url; link.textContent = label; section.appendChild(link);
			});
			form.appendChild(section);
		}
		if (!form.querySelector('.ss-about-link')) {
			var aboutLink = document.createElement('a');
			aboutLink.className = 'ss-about-link';
			aboutLink.href = 'schoolsafe-about.php';
			aboutLink.textContent = '\u00c0 propos de SchoolSafe';
			form.appendChild(aboutLink);
		}
		if (!form.querySelector('.ss-settings-close')) {
			var close = document.createElement('button'); close.type = 'button';
			close.className = 'ss-settings-close'; close.textContent = '\u00d7';
			close.setAttribute('aria-label', 'Fermer les parametres');
			close.addEventListener('click', function () { setSettings(false); document.getElementById('ss-settings-button')?.focus(); });
			var heading = document.createElement('div'); heading.className = 'ss-settings-heading';
			var title = document.createElement('strong'); title.textContent = 'Profil et param\u00e8tres';
			heading.appendChild(title); heading.appendChild(close); form.prepend(heading);
		}
		var button = document.getElementById('ss-settings-button');
		if (!button) {
			button = document.createElement('button'); button.type = 'button'; button.id = 'ss-settings-button';
			button.textContent = 'Profil et param\u00e8tres'; button.setAttribute('aria-controls', form.id);
			button.addEventListener('click', function () { setSettings(!document.body.classList.contains('ss-settings-open')); });
			var settingsRow = document.createElement('li'); settingsRow.className = 'ss-settings-row';
			settingsRow.appendChild(button);
			(menu.querySelector('.adminmenu') || menu).appendChild(settingsRow);
		}
		button.setAttribute('aria-expanded', String(document.body.classList.contains('ss-settings-open')));
		var summary = document.getElementById('ss-context-summary');
		if (!summary) {
			summary = document.createElement('button'); summary.type = 'button'; summary.id = 'ss-context-summary';
			summary.title = 'Changer le contexte scolaire';
			summary.addEventListener('click', function () { setSettings(true); });
			document.getElementById('menu-top').appendChild(summary);
		}
		summary.textContent = Array.from(form.querySelectorAll('select')).map(function (select) { return select.selectedOptions[0]?.textContent.trim(); }).filter(Boolean).join(' \u00b7 ');
	}
	function setMobilePanel(panel) {
		if (panel !== 'context') markOpen();
		var menu = document.getElementById('menu');
		if (!menu) return;
		if (!panel) setSettings(false);
		document.body.setAttribute('data-ss-mobile-panel', panel);
		menu.classList.toggle('hide', menuIsNarrow() && !panel);
		menu.classList.toggle('slide', menuIsNarrow() && !!panel);
		document.body.style.overflow = panel ? 'hidden' : '';
		var content = document.getElementById('body');
		var footer = document.getElementById('footer');
		if (content) content.inert = !!panel;
		if (footer) footer.inert = !!panel;
		var button = document.getElementById('ss-mobile-menu');
		if (button) button.setAttribute('aria-expanded', String(!!panel));
	}
	function buildMobileNavigation() {
		if (document.getElementById('ss-mobile-navigation')) return;
		var nav = document.createElement('nav');
		nav.id = 'ss-mobile-navigation'; nav.setAttribute('aria-label', 'Navigation mobile');
		function selectButton(selected) {
			Array.from(nav.children).forEach(function (item) {
				item.classList.toggle('ss-nav-active', item === selected);
			});
		}
		nav.addEventListener('click', function (event) {
			var item = event.target.closest('a, button');
			if (item && item.parentElement === nav) selectButton(item);
		});
		var portal = document.querySelector('#menu-top > a[href]');
		if (portal) {
			var dashboard = document.createElement('a');
			dashboard.href = portal.getAttribute('href');
			dashboard.innerHTML = '<span class="module-icon School_Setup" aria-hidden="true"></span><span>Tableau de bord</span>';
			dashboard.addEventListener('click', function () { setMobilePanel(''); });
			nav.appendChild(dashboard);
			if (location.search.includes('modname=misc/Portal.php')) selectButton(dashboard);
		}
		var jaspe = document.createElement('button');
		jaspe.type = 'button'; jaspe.id = 'ss-jaspe-button';
		jaspe.title = 'Ouvrir JASPE';
		jaspe.setAttribute('aria-haspopup', 'dialog');
		jaspe.setAttribute('aria-controls', 'ss-jaspe-dialog');
		jaspe.setAttribute('aria-expanded', 'false');
		jaspe.addEventListener('click', function () {
			setMobilePanel('');
			document.getElementById('ss-watch-dialog')?.close();
			var dialog = document.getElementById('ss-jaspe-dialog');
			if (!dialog) {
				var dialogStyles = document.createElement('link');
				dialogStyles.rel = 'stylesheet';
				dialogStyles.href = 'assets/themes/SchoolSafeCodex/mobile-navigation.css?v=19';
				document.head.appendChild(dialogStyles);
				dialog = document.createElement('dialog');
				dialog.id = 'ss-jaspe-dialog'; dialog.setAttribute('aria-label', 'JASPE, assistante SchoolSafe');
				var close = document.createElement('button');
				close.type = 'button'; close.className = 'ss-jaspe-close';
				close.textContent = '\u00d7'; close.title = 'Fermer JASPE';
				close.setAttribute('aria-label', 'Fermer JASPE');
				close.addEventListener('click', function () { dialog.close(); });
				var frame = document.createElement('iframe');
				frame.title = 'Conversation avec JASPE';
				frame.src = 'Modules.php?modname=SchoolSafe_Jaspe/Assistant.php&ss_companion=1';
				frame.addEventListener('load', function () {
					var doc = frame.contentDocument;
					if (!doc || !doc.getElementById('jaspe-native')) {
						frame.hidden = true;
						var notice = dialog.querySelector('.ss-jaspe-unavailable');
						if (!notice) {
							notice = document.createElement('p');
							notice.className = 'ss-jaspe-unavailable'; notice.setAttribute('role', 'status');
							dialog.appendChild(notice);
						}
						notice.textContent = 'JASPE est indisponible. Actualisez la page et verifiez votre connexion.';
						return;
					}
					frame.hidden = false; dialog.querySelector('.ss-jaspe-unavailable')?.remove();
					syncJaspeTheme();
					// Keep the keyboard closed on touch devices until the user chooses to write.
					if (window.matchMedia('(pointer: fine)').matches) doc.getElementById('jaspe-question')?.focus();
					doc.addEventListener('keydown', function (event) {
						if (event.key === 'Escape') { event.preventDefault(); dialog.close(); }
					});
				});
				dialog.appendChild(close); dialog.appendChild(frame);
				dialog.addEventListener('close', function () {
					jaspe.setAttribute('aria-expanded', 'false'); jaspe.classList.remove('ss-nav-active');
					var headerJaspe = document.getElementById('ss-header-jaspe');
					if (headerJaspe) headerJaspe.setAttribute('aria-expanded', 'false');
					(menuIsNarrow() ? jaspe : headerJaspe || jaspe).focus();
				});
				document.body.appendChild(dialog);
			}
			if (dialog.open) { dialog.close(); return; }
			dialog.show(); jaspe.setAttribute('aria-expanded', 'true');
			document.getElementById('ss-header-jaspe')?.setAttribute('aria-expanded', 'true');
		});
		jaspe.innerHTML = '<span class="ss-jaspe-portrait" aria-hidden="true"><img src="modules/SchoolSafe_Jaspe/presenter.png" alt=""></span><span>JASPE</span>';
		nav.appendChild(jaspe);
		var watch = document.createElement('button');
		watch.type = 'button'; watch.id = 'ss-watch-button'; watch.title = 'SchoolSafe Watch';
		watch.setAttribute('aria-haspopup', 'dialog'); watch.setAttribute('aria-controls', 'ss-watch-dialog');
		watch.setAttribute('aria-expanded', 'false');
		watch.innerHTML = '<span class="ss-watch-icon" aria-hidden="true"><span>12:00</span></span><span>Watch</span>';
		watch.addEventListener('click', function () {
			setMobilePanel(''); document.getElementById('ss-jaspe-dialog')?.close();
			var panel = document.getElementById('ss-watch-dialog');
			if (!panel) {
				panel = document.createElement('dialog'); panel.id = 'ss-watch-dialog';
				panel.setAttribute('aria-labelledby', 'ss-watch-title');
				panel.innerHTML = '<button type="button" class="ss-watch-close" aria-label="Fermer SchoolSafe Watch">\u00d7</button><h2 id="ss-watch-title">SchoolSafe Watch</h2><p>Fonctionnalit\u00e9 en pr\u00e9paration</p><p>Aucune montre raccord\u00e9e \u00e0 cet espace.</p>';
				panel.querySelector('button').addEventListener('click', function () { panel.close(); });
				panel.addEventListener('close', function () {
					watch.setAttribute('aria-expanded', 'false');
					var headerWatch = document.getElementById('ss-header-watch');
					if (headerWatch) headerWatch.setAttribute('aria-expanded', 'false');
					(menuIsNarrow() ? watch : headerWatch || watch).focus();
				});
				document.body.appendChild(panel);
			}
			if (panel.open) panel.close();
			else { panel.show(); watch.setAttribute('aria-expanded', 'true'); document.getElementById('ss-header-watch')?.setAttribute('aria-expanded', 'true'); panel.querySelector('button').focus(); }
		});
		nav.appendChild(watch);
		var button = document.createElement('button');
		button.type = 'button'; button.id = 'ss-mobile-menu';
		button.setAttribute('aria-controls', 'menu'); button.setAttribute('aria-expanded', 'false');
		var original = document.querySelector('#BottomButtonMenu svg');
		if (original) button.appendChild(original.cloneNode(true));
		var label = document.createElement('span'); label.textContent = 'Menu'; button.appendChild(label);
		button.addEventListener('click', function () {
			document.getElementById('ss-watch-dialog')?.close();
			document.getElementById('ss-jaspe-dialog')?.close();
			var open = document.body.getAttribute('data-ss-mobile-panel') === 'primary';
			setMobilePanel(open ? '' : 'primary');
			if (!open) document.querySelector('#menu .menu-top')?.focus();
		});
		nav.appendChild(button); document.body.appendChild(nav);
	}

	function setMobileMenu(open) {
		setMobilePanel(open ? 'primary' : '');
	}

	function onMenuButton(e) {
		if (!menuIsNarrow()) {
			return;
		}

		e.preventDefault();
		e.stopPropagation();

		var menu = document.getElementById('menu');
		var open = menu && menu.classList.contains('slide') && !menu.classList.contains('hide');

		setMobileMenu(!open);
	}
	function decorateSubmenus() {
		document.querySelectorAll('.wp-submenu').forEach(function (submenu) {
			if (submenu.querySelector('.ss-context-close')) return;
			var item = document.createElement('li'); item.className = 'ss-context-heading';
			var close = document.createElement('button'); close.type = 'button'; close.className = 'ss-context-close';
			close.textContent = '\u00d7'; close.setAttribute('aria-label', 'Fermer le sous-menu');
			close.addEventListener('click', function () {
				var top = submenu.parentElement.querySelector('.menu-top');
				if (menuIsNarrow()) setMobilePanel('primary');
				{
					submenu.parentElement.classList.remove('ss-open');
					top.removeAttribute('id'); top.setAttribute('aria-expanded', 'false');
				}
				top.focus();
			});
			var title = document.createElement('strong'); title.textContent = submenu.parentElement.querySelector('.menu-top')?.textContent.trim() || 'Sous-menu';
			item.appendChild(title); item.appendChild(close); submenu.prepend(item);
		});
	}
	function prepareHeaderProfile() {
		var header = document.getElementById('footerwrap');
		if (!header) return;
		var french = document.documentElement.lang.indexOf('fr') === 0;
		function label(fr, en) { return french ? fr : en; }
		var reportButton = document.getElementById('BottomButtonSaveReport');
		var content = document.getElementById('body');
		if (reportButton && content && header.contains(reportButton)) {
			var reportActions = document.createElement('div'); reportActions.className = 'ss-report-actions';
			reportActions.appendChild(reportButton); content.prepend(reportActions);
		}
		['BottomButtonPrint', 'BottomButtonHelp'].forEach(function (id) {
			var button = document.getElementById(id);
			if (button) button.hidden = true;
		});
		var logout = header.querySelector('a[href*="modfunc=logout"]');
		if (logout) {
			logout.classList.add('ss-header-logout');
			logout.setAttribute('aria-label', label('D\u00e9connexion', 'Log out'));
			logout.title = label('D\u00e9connexion', 'Log out');
		}
		if (!header.querySelector('.ss-header-tools')) {
			var tools = document.createElement('div'); tools.className = 'ss-header-tools';
			tools.setAttribute('role', 'group'); tools.setAttribute('aria-label', label('Actions du compte', 'Account actions'));
			function headerAction(label, icon, url) {
				var action = document.createElement(url ? 'a' : 'button');
				if (url) action.href = url; else action.type = 'button';
				action.className = 'ss-header-action'; action.setAttribute('aria-label', label); action.title = label;
				var image = document.createElement('img'); image.src = 'assets/themes/SchoolSafeCodex/navigation-icons/' + icon + '.svg'; image.alt = '';
				var tip = document.createElement('span'); tip.className = 'ss-header-tooltip'; tip.textContent = label; tip.setAttribute('role', 'tooltip');
				action.append(image, tip); tools.appendChild(action); return action;
			}
			var notifications = headerAction('Notifications', 'MessagesSquare', headerNotificationUrl);
			var headerJaspe = headerAction('JASPE', 'MessagesSquare', null);
			headerJaspe.id = 'ss-header-jaspe'; headerJaspe.classList.add('ss-desktop-companion');
			headerJaspe.querySelector('img').src = 'modules/SchoolSafe_Jaspe/presenter.png';
			var portrait = document.createElement('span'); portrait.className = 'ss-header-jaspe-portrait'; portrait.setAttribute('aria-hidden', 'true');
			headerJaspe.insertBefore(portrait, headerJaspe.firstChild); portrait.appendChild(headerJaspe.querySelector('img'));
			headerJaspe.setAttribute('aria-haspopup', 'dialog'); headerJaspe.setAttribute('aria-controls', 'ss-jaspe-dialog'); headerJaspe.setAttribute('aria-expanded', 'false');
			headerJaspe.addEventListener('click', function () { document.getElementById('ss-jaspe-button')?.click(); });
			var headerWatch = headerAction('SchoolSafe Watch', 'FolderCog', null);
			headerWatch.id = 'ss-header-watch'; headerWatch.classList.add('ss-desktop-companion');
			var watchFace = document.createElement('span'); watchFace.className = 'ss-watch-icon'; watchFace.setAttribute('aria-hidden', 'true'); watchFace.innerHTML = '<span>12:00</span>';
			headerWatch.querySelector('img').replaceWith(watchFace);
			headerWatch.setAttribute('aria-haspopup', 'dialog'); headerWatch.setAttribute('aria-controls', 'ss-watch-dialog'); headerWatch.setAttribute('aria-expanded', 'false');
			headerWatch.addEventListener('click', function () { document.getElementById('ss-watch-button')?.click(); });
			if (!headerNotificationUrl) {
				notifications.setAttribute('aria-disabled', 'true');
				notifications.title = label('Notifications : non disponibles pour ce profil', 'Notifications: unavailable for this profile');
			}
			var settings = headerAction(label('Param\u00e8tres', 'Settings'), 'FolderCog', null);
			settings.setAttribute('aria-haspopup', 'dialog');
			settings.addEventListener('click', function () { document.getElementById('ss-settings-button')?.click(); });
			var background = headerAction(label('Fond bleu', 'Blue background'), 'Palette', null);
			background.id = 'ss-background-button';
			function updateBackgroundButton() {
				background.dataset.background = document.documentElement.dataset.ssBackground;
				var nextBackground = backgroundChoices[(backgroundChoices.indexOf(document.documentElement.dataset.ssBackground) + 1) % backgroundChoices.length];
				var names = {white: label('blanc', 'white'), blue: label('bleu clair', 'light blue'), gray: label('gris clair', 'light gray'), mint: label('vert menthe', 'mint green'), rose: label('rose clair', 'light pink'), yellow: label('jaune clair', 'light yellow'), night: label('bleu nuit', 'midnight blue')};
				background.setAttribute('aria-label', label('Changer le fond', 'Change background'));
				background.title = label('Passer au fond ', 'Switch to ') + names[nextBackground] + (french ? '' : ' background');
				background.querySelector('.ss-header-tooltip').textContent = background.title;
				syncJaspeTheme();
			}
			background.addEventListener('click', function () {
				var next = backgroundChoices[(backgroundChoices.indexOf(document.documentElement.dataset.ssBackground) + 1) % backgroundChoices.length];
				document.documentElement.dataset.ssBackground = next;
				try { localStorage.setItem('schoolsafe-background', next); } catch (_) {}
				updateBackgroundButton();
			});
			updateBackgroundButton();
			var nativeLanguages = Array.from(header.querySelectorAll('.ss-lang-link')).filter(function (link) {
				var locale = new URL(link.href, location.href).searchParams.get('locale') || '';
				return /^(fr|en)[_-]/.test(locale);
			});
			if (nativeLanguages.length) {
				var language = headerAction(label('Langue', 'Language'), 'Languages', null);
				language.id = 'ss-language-button'; language.setAttribute('aria-expanded', 'false');
				language.setAttribute('aria-controls', 'ss-header-languages');
				var languages = document.createElement('div'); languages.id = 'ss-header-languages';
				languages.className = 'ss-header-languages'; languages.hidden = true;
				languages.setAttribute('aria-label', label('Choisir la langue', 'Choose language'));
				nativeLanguages.forEach(function (nativeLink) {
					var link = document.createElement('a'); var url = new URL(nativeLink.href, location.href);
					['modfunc', 'token', 'delete_ok', 'LO_save', 'bottomfunc'].forEach(function (key) { url.searchParams.delete(key); });
					link.href = url.href;
					link.target = '_top';
					link.textContent = url.searchParams.get('locale').indexOf('fr') === 0 ? 'Fran\u00e7ais' : 'English';
					if (nativeLink.classList.contains('is-active')) link.setAttribute('aria-current', 'true');
					languages.appendChild(link);
				});
				function closeLanguages() { languages.hidden = true; language.setAttribute('aria-expanded', 'false'); }
				language.addEventListener('click', function () {
					languages.hidden = !languages.hidden; language.setAttribute('aria-expanded', String(!languages.hidden));
				});
				document.addEventListener('click', function (event) { if (!language.contains(event.target) && !languages.contains(event.target)) closeLanguages(); });
				document.addEventListener('keydown', function (event) {
					if (event.key === 'Escape' && !languages.hidden) { closeLanguages(); language.focus(); }
				});
				tools.appendChild(languages);
			}
			if (logout) {
				logout.classList.add('ss-header-action');
				var tip = document.createElement('span'); tip.className = 'ss-header-tooltip'; tip.textContent = label('D\u00e9connexion', 'Log out');
				logout.appendChild(tip); tools.appendChild(logout);
			}
			header.appendChild(tools);
		}
		if (!centralProfile || header.querySelector('.ss-header-profile')) return;
		var link = document.createElement('a');
		link.className = 'ss-header-profile'; link.href = centralProfile.url;
		link.title = label('Mon profil : ', 'My profile: ') + centralProfile.name;
		link.setAttribute('aria-label', link.title);
		var avatar = document.createElement('span'); avatar.className = 'ss-header-avatar';
		var initials = centralProfile.name.split(/\s+/).filter(Boolean).slice(0, 2).map(function (part) { return part[0]; }).join('').toUpperCase() || '?';
		avatar.textContent = initials;
		if (typeof centralProfile.photo === 'string' && centralProfile.photo && !/^(?:[a-z]+:|\/\/)/i.test(centralProfile.photo)) {
			var photo = document.createElement('img'); photo.src = centralProfile.photo; photo.alt = '';
			photo.addEventListener('error', function () { avatar.textContent = initials; });
			avatar.replaceChildren(photo);
		}
		link.appendChild(avatar); header.appendChild(link);
	}
	// Navigation metadata selects existing UI destinations; server guards remain authoritative.
	function schoolSafeCentralDestination(value) {
		if (typeof value !== 'string') return false;
		if (/^[a-z-]+\.php$/.test(value)) return true;
		if (!/^Modules\.php\?modname=SchoolSafe\/[A-Za-z]+\.php$/.test(value)) return false;
		var allowed = ['Studio','RemediationRead','FamilyRead','ScanVerify','ScanArrival','ScanPickup','ScanFees','ArrivalAttendance','FeeModelsRead','RemediationPaymentsRead','Notifications'];
		return allowed.includes(value.slice('Modules.php?modname=SchoolSafe/'.length, -4));
	}
	async function onReady() {
		if (menuIsNarrow()) setMobilePanel('');
		try {
			var response = await fetch('/central-navigation.php', {credentials:'same-origin', cache:'no-store', signal:AbortSignal.timeout(4000)});
			if (response.ok) {
				var manifest = await response.json();
				headerNotificationUrl = Array.isArray(manifest.links) && manifest.links.some(function (entry) {
					return entry.url === 'Modules.php?modname=SchoolSafe/Notifications.php';
				}) ? 'Modules.php?modname=SchoolSafe/Notifications.php' : null;
				if (manifest.profile && typeof manifest.profile.name === 'string' && /^Modules\.php\?modname=Users\/User\.php&category_id=1&staff_id=\d+$/.test(manifest.profile.url)) centralProfile = manifest.profile;
				centralEntries = Array.isArray(manifest.links) ? manifest.links.filter(function (entry) {
					return branchDefinitions.some(function (definition) { return definition[0] === entry.branch; }) && schoolSafeCentralDestination(entry.url) && typeof entry.title === 'string' && typeof entry.group === 'string';
				}) : [];
			}
		} catch (error) { centralEntries = []; }
		prepareHeaderProfile();
		buildMobileNavigation();
		window.matchMedia('(max-width: 700px)').addEventListener('change', function () {
			setMobilePanel('');
			if (!menuIsNarrow()) document.getElementById('menu')?.classList.remove('hide', 'slide');
		});
		window.addEventListener('resize', function () {
			if (!menuIsNarrow()) {
				setMobilePanel('');
				document.body.style.overflow = '';
				var menu = document.getElementById('menu');
				if (menu) menu.classList.remove('hide', 'slide');
			}
		});
		document.addEventListener('keydown', function (e) {
			var target = e.target;
			if (e.key === 'ArrowRight' && target.matches && target.matches('.menu-top[aria-expanded="true"]')) {
				var firstLink = target.parentElement.querySelector('.wp-submenu a');
				if (firstLink) { e.preventDefault(); firstLink.focus(); }
				return;
			}
			if (e.key === 'ArrowLeft' && target.closest && target.closest('.wp-submenu')) {
				e.preventDefault();
				if (menuIsNarrow()) setMobilePanel('primary');
				target.closest('.menu-module').querySelector('.menu-top').focus();
				return;
			}
			if (e.key === 'Tab' && menuIsNarrow()) {
				var panel = document.body.getAttribute('data-ss-mobile-panel');
				if (panel) {
					var selector = '#menu';
					var controls = Array.from(document.querySelectorAll(selector + ' a, ' + selector + ' button, ' + selector + ' select, #ss-mobile-navigation a, #ss-mobile-navigation button:not(:disabled)')).filter(function (el) { return el.getClientRects().length && !el.closest('[inert]'); });
					var first = controls[0], last = controls[controls.length - 1];
					if (e.shiftKey && target === first) { e.preventDefault(); last?.focus(); }
					else if (!e.shiftKey && target === last) { e.preventDefault(); first?.focus(); }
				}
			}
			if (e.key !== 'Escape') return;
			if (document.body.classList.contains('ss-settings-open')) { setSettings(false); document.getElementById('ss-settings-button')?.focus(); return; }
			if (menuIsNarrow()) { setMobilePanel(''); document.getElementById('ss-mobile-menu')?.focus(); return; }
			var current = document.querySelector('.menu-module.ss-open > .menu-top');
			if (current) {
				current.parentElement.classList.remove('ss-open');
				current.removeAttribute('id');
				current.setAttribute('aria-expanded', 'false');
				current.focus();
			}
			if (menuIsNarrow()) setMobileMenu(false);
		});
		var menuButton = document.getElementById('BottomButtonMenu');

		if (menuButton) {
			menuButton.addEventListener('click', onMenuButton, true);
		}

		if (menuIsNarrow()) {
			var menu = document.getElementById('menu');

			if (menu && !menu.classList.contains('slide')) {
				menu.classList.add('hide');
			}
		}

		bind();
		presentNativePage();

		if (!window.jQuery) {
			return;
		}

		window.jQuery(document).on('ajaxPrepare', '#menu, #menu-top', bind);
		window.jQuery(document).on('ajaxPrepare', '#body', function () {
			if (menuIsNarrow()) setMobilePanel('');
			presentNativePage();
		});
		function themeSecondarySurface() {
			var source = document.getElementById('body');
			var surface = document.getElementById('cboxLoadedContent');
			if (!source || !surface || !source.dataset.ssSection || destination(new URL(location.href).searchParams.get('modname') || '') === 'pilotage') return;
			var computed = getComputedStyle(source);
			['--school-accent','--school-focus','--school-ink','--school-muted','--school-line','--school-wash'].forEach(function (name) {
				surface.style.setProperty(name, computed.getPropertyValue(name));
			});
			surface.classList.add('ss-secondary-surface');
			presentSchoolContent(surface, source.dataset.ssSection);
			presentFormLayout(surface);
		}
		window.jQuery(document).on('cbox_complete', themeSecondarySurface);
		window.jQuery(document).on('ajaxPrepare', '#cboxLoadedContent', themeSecondarySurface);

		window.jQuery(document).on('click', function (e) {
			if (!menuIsNarrow()) {
				return;
			}

			var menu = document.getElementById('menu');

			if (!menu || !menu.classList.contains('slide') || menu.classList.contains('hide')) {
				return;
			}

			if (e.target.closest && e.target.closest('#menu .adminmenu, #menu .logo, #menu #menu-top, #menu .ss-lang, #ss-settings-button, #BottomButtonMenu, #footer, #ss-mobile-navigation')) {
				return;
			}

			setMobileMenu(false);
		});
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', onReady, {once:true});
	else onReady();
})();
(function () {
    function setupAssistantLink() {
        var links = document.querySelectorAll('#menu a');
        var setup, jaspe;
        Array.prototype.forEach.call(links, function (link) {
            if (/^Assistant de configuration (Rosario|School Safe)$/.test(link.textContent.trim())) setup = link;
            if (link.getAttribute('href') === 'Modules.php?modname=SchoolSafe_Jaspe/Assistant.php') jaspe = link;
        });
        if (!setup) return;
        setup.href = 'Modules.php?modname=misc/Portal.php&ss_setup=1';
        if (jaspe && jaspe.closest('li') && jaspe.closest('li').parentNode) {
            var item = document.createElement('li');
            item.className = 'ss-setup-assistant-link';
            item.appendChild(setup);
            jaspe.closest('li').insertAdjacentElement('afterend', item);
        }
    }
    window.addEventListener('load', setupAssistantLink, { once: true });
})();
