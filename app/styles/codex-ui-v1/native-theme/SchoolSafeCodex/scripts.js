/* SchoolSafe menu. A main function opens its pages. A second press closes them. */
(function () {
	var branchDefinitions = [
		['pilotage', 'Pilotage', 'School_Setup'], ['school', '\u00c9cole', 'School_Setup'],
		['pedagogy', 'P\u00e9dagogie', 'Grades'], ['security', 'S\u00e9curit\u00e9 et contr\u00f4le', 'Discipline'],
		['finance', 'Finance', 'Student_Billing'], ['feeControl', 'Contr\u00f4le des frais', 'Student_Billing'],
		['accounting', 'Comptabilit\u00e9', 'Accounting'], ['people', 'Personnel', 'Users'],
		['inventory', 'Stock / Inventaire', 'Resources'], ['communication', 'Communication', 'Resources'],
		['care', 'Vie et bien-\u00eatre', 'Food_Service'], ['administration', 'Administration', 'Users'],
		['reports', 'Contr\u00f4le et rapports', 'Grades']
	];
	function destination(path) {
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
			var heading = module.querySelector('.menu-top').textContent.trim();
			var section = '';
			Array.from(module.querySelectorAll('.wp-submenu > li')).forEach(function (item) {
				if (item.classList.contains('menu-inter')) { section = item.textContent.trim(); return; }
				var link = item.querySelector('a[href]');
				if (!link) return;
				var path = new URL(link.href, window.location.href).searchParams.get('modname') || '';
				var branch = /^(reports|rapports)$/i.test(section) ? 'reports' : destination(path);
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
			home.appendChild(homeLink); groups.pilotage = [{item:home, heading:'Vue g\u00e9n\u00e9rale'}];
		}
		branchDefinitions.forEach(function (definition) {
			var entries = groups[definition[0]];
			if (!entries || !entries.length) return;
			var module = document.createElement('li');
			module.className = 'menu-module ss-branch-' + definition[0];
			module.setAttribute('data-ss-branch', definition[0]);
			var top = document.createElement('a');
			top.className = 'menu-top';
			top.href = entries[0].item.querySelector('a').getAttribute('href');
			var icon = document.createElement('span');
			icon.className = 'module-icon ' + definition[2];
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
				var schoolOrder = ['\u00c9l\u00e8ves et inscriptions', 'Familles et acc\u00e8s', 'Organisation scolaire', 'R\u00e9f\u00e9rentiels \u00e9l\u00e8ves'];
				order.sort(function (a, b) { return schoolOrder.indexOf(a) - schoolOrder.indexOf(b); });
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

		var current = document.getElementById('selectedModuleLink');

		if (current && current.parentElement) {
			current.parentElement.classList.add('ss-open');
			current.setAttribute('aria-expanded', 'true');
		}
	}

	function bind() {
		organizeMenu();
		decorateSubmenus();
		prepareSettings();
		var brand = document.querySelector('#menu-top > a[href]');
		if (brand && !brand.querySelector('.ss-brand-name')) {
			var name = document.createElement('strong'); name.className = 'ss-brand-name';
			name.textContent = 'SchoolSafe'; brand.appendChild(name);
		}
		if (window.jQuery && window.submenuOnTouch) {
			window.jQuery('.adminmenu .menu-top').off('click touch', window.submenuOnTouch);
			window.jQuery('.adminmenu .menu-top').off('mouseover focus');
		}

		var tops = document.querySelectorAll('.adminmenu .menu-top');
		var i;

		for (i = 0; i < tops.length; i++) {
			var submenu = tops[i].parentElement.querySelector('.wp-submenu');
			if (submenu) {
				submenu.setAttribute('data-ss-title', tops[i].textContent.trim());
				submenu.setAttribute('aria-label', tops[i].textContent.trim());
				if (submenu.id) tops[i].setAttribute('aria-controls', submenu.id);
			}
			tops[i].setAttribute('aria-expanded', tops[i].id === 'selectedModuleLink' ? 'true' : 'false');

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
		}
		markOpen();
		if (menuIsNarrow()) setMobilePanel('');
	}

	function onToggle(e) {
		if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) {
			return;
		}

		e.preventDefault();
		e.stopPropagation();
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

		if (!wasOpen || menuIsNarrow()) {
			link.id = 'selectedModuleLink';
			link.setAttribute('aria-expanded', 'true');
			module.classList.add('ss-open');
			if (menuIsNarrow()) {
				setMobilePanel('context');
				module.querySelector('.ss-context-close').focus();
			}
		}
	}

	function menuIsNarrow() {
		return window.matchMedia('(max-width: 700px)').matches;
	}
	function setSettings(open) {
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
			menu.appendChild(button);
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
		var portal = document.querySelector('#menu-top > a[href]');
		if (portal) {
			var dashboard = document.createElement('a');
			dashboard.href = portal.getAttribute('href');
			dashboard.innerHTML = '<span class="module-icon School_Setup" aria-hidden="true"></span><span>Tableau de bord</span>';
			dashboard.addEventListener('click', function () { setMobilePanel(''); });
			nav.appendChild(dashboard);
		}
		var jaspe = document.createElement('button');
		jaspe.type = 'button'; jaspe.disabled = true;
		jaspe.title = 'JASPE : raccordement non disponible dans ce theme natif';
		// Lucide MessageCircle, matching the approved SchoolSafe model.
		jaspe.innerHTML = '<svg class="lucide lucide-message-circle" viewBox="0 0 24 24" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path></svg><span>JASPE</span>';
		nav.appendChild(jaspe);
		var button = document.createElement('button');
		button.type = 'button'; button.id = 'ss-mobile-menu';
		button.setAttribute('aria-controls', 'menu'); button.setAttribute('aria-expanded', 'false');
		var original = document.querySelector('#BottomButtonMenu svg');
		if (original) button.appendChild(original.cloneNode(true));
		var label = document.createElement('span'); label.textContent = 'Menu'; button.appendChild(label);
		button.addEventListener('click', function () {
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
				else {
					submenu.parentElement.classList.remove('ss-open');
					top.removeAttribute('id'); top.setAttribute('aria-expanded', 'false');
				}
				top.focus();
			});
			var title = document.createElement('strong'); title.textContent = submenu.parentElement.querySelector('.menu-top')?.textContent.trim() || 'Sous-menu';
			item.appendChild(title); item.appendChild(close); submenu.prepend(item);
		});
	}
	function onReady() {
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
					var selector = panel === 'context' ? '.ss-open .wp-submenu' : '#menu';
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
		window.jQuery(document).on('ajaxPrepare', '#body', presentNativePage);

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

	window.addEventListener('load', onReady);
})();
