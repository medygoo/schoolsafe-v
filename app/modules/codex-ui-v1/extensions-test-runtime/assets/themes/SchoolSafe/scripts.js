/* SchoolSafe menu. A main function opens its pages. A second press closes them. */
(function () {
	function markOpen() {
		var modules = document.querySelectorAll('.menu-module');
		var i;

		for (i = 0; i < modules.length; i++) {
			modules[i].classList.remove('ss-open');
		}

		var current = document.getElementById('selectedModuleLink');

		if (current && current.parentElement) {
			current.parentElement.classList.add('ss-open');
			current.setAttribute('aria-expanded', 'true');
		}
	}

	function bind() {
		if (window.jQuery && window.submenuOnTouch) {
			window.jQuery('.adminmenu .menu-top').off('click touch', window.submenuOnTouch);
			window.jQuery('.adminmenu .menu-top').off('mouseover focus');
		}

		var tops = document.querySelectorAll('.adminmenu .menu-top');
		var i;

		for (i = 0; i < tops.length; i++) {
			tops[i].setAttribute('aria-expanded', tops[i].id === 'selectedModuleLink' ? 'true' : 'false');

			if (tops[i].getAttribute('data-ss-menu') === '1') {
				continue;
			}

			tops[i].setAttribute('data-ss-menu', '1');
			tops[i].addEventListener('click', onToggle, true);
		}

		markOpen();
	}

	function onToggle(e) {
		if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) {
			return;
		}

		e.preventDefault();
		e.stopPropagation();

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
			link.id = 'selectedModuleLink';
			link.setAttribute('aria-expanded', 'true');
			module.classList.add('ss-open');
		}
	}

	function menuIsNarrow() {
		return window.matchMedia('(max-width: 874px)').matches;
	}

	function setMobileMenu(open) {
		var menu = document.getElementById('menu');

		if (!menu) {
			return;
		}

		if (open) {
			menu.classList.remove('hide');
			void menu.offsetHeight;
			menu.classList.add('slide');
			document.body.style.overflow = 'hidden';
			return;
		}

		menu.classList.remove('slide');
		window.setTimeout(function () {
			menu.classList.add('hide');
		}, 300);
		document.body.style.overflow = '';
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
	function onReady() {
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

		if (!window.jQuery) {
			return;
		}

		window.jQuery(document).on('ajaxPrepare', '#menu', bind);

		window.jQuery(document).on('click', function (e) {
			if (!menuIsNarrow()) {
				return;
			}

			var menu = document.getElementById('menu');

			if (!menu || !menu.classList.contains('slide') || menu.classList.contains('hide')) {
				return;
			}

			if (e.target.closest && e.target.closest('#menu .adminmenu, #menu .logo, #menu #menu-top, #menu .ss-lang, #BottomButtonMenu, #footer')) {
				return;
			}

			setMobileMenu(false);
		});
	}

	window.addEventListener('load', onReady);
})();
