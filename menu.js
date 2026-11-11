(function () {
    'use strict';

    var playHubUrl = '/admin/addons/PlayHub/';

    function hasPlayHubLink() {
        return !!document.querySelector('a[href="' + playHubUrl + '"], a[href^="' + playHubUrl + '?"]');
    }

    function addToAioMenu() {
        var aioMenu = document.getElementById('menu_ferramentas_aio');
        if (!aioMenu || hasPlayHubLink()) return false;
        var dropdown = aioMenu.querySelector('.navbar-dropdown');
        if (!dropdown) return false;

        var link = document.createElement('a');
        link.href = playHubUrl;
        link.className = 'navbar-item';
        link.innerHTML = '<i class="bi-play-circle"></i>&nbsp; PlayHub';
        var reference = dropdown.querySelector('a[href*="notifica_email"], a[href*="notifica_sms"]');
        dropdown.insertBefore(link, reference || null);
        return true;
    }

    function addToNativeMenu() {
        if (hasPlayHubLink()) return true;
        if (!window.add_menu || typeof window.add_menu.opcoes !== 'function') return false;
        window.add_menu.opcoes(JSON.stringify({ plink: playHubUrl, ptext: 'PlayHub' }));
        return true;
    }

    var mountAttempts = 0;
    function mountPlayHubMenu() {
        if (addToAioMenu()) return;
        if (document.getElementById('script_menu_aio') && mountAttempts < 10) {
            mountAttempts += 1;
            window.setTimeout(mountPlayHubMenu, 250);
            return;
        }
        if (addToNativeMenu()) return;
        if (mountAttempts < 10) {
            mountAttempts += 1;
            window.setTimeout(mountPlayHubMenu, 250);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountPlayHubMenu, { once: true });
    } else {
        mountPlayHubMenu();
    }
})();
