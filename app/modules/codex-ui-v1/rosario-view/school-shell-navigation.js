(() => {
    'use strict';
    const menu = document.querySelector('.school-menu-disclosure');
    if (!menu) return;
    const mobile = window.matchMedia('(max-width:700px)');
    const adaptMenu = () => { menu.open = !mobile.matches; };
    adaptMenu();
    mobile.addEventListener('change', adaptMenu);
})();
