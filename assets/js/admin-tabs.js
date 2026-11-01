(function () {
    var shell = document.querySelector('[data-admin-tabs]');
    if (!shell) {
        return;
    }

    var allowed = ['haberler', 'dogum-gunleri', 'yeni-katilanlar', 'yemek-menusu', 'aci-kaybimiz', 'etkinlik-takvimi', 'duyuru-banneri', 'anketler', 'egitimler'];

    function normalizeHash(hash) {
        if (!hash || hash === '#') {
            return '';
        }
        var h = hash.replace(/^#/, '').replace(/_/g, '-');
        return allowed.indexOf(h) !== -1 ? h : '';
    }

    function showPane(name) {
        if (allowed.indexOf(name) === -1) {
            name = 'haberler';
        }

        var tabs = shell.querySelectorAll('.admin-tab[data-tab-target]');
        var panes = shell.querySelectorAll('[data-admin-pane]');

        tabs.forEach(function (tab) {
            var on = tab.getAttribute('data-tab-target') === name;
            tab.classList.toggle('is-active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
        });

        panes.forEach(function (pane) {
            var on = pane.getAttribute('data-admin-pane') === name;
            if (on) {
                pane.removeAttribute('hidden');
            } else {
                pane.setAttribute('hidden', 'hidden');
            }
        });

        try {
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '#' + name);
            } else {
                window.location.hash = name;
            }
        } catch (e) {
            /* ignore */
        }
    }

    var fromServer = shell.getAttribute('data-default-pane') || 'haberler';
    var fromHash = normalizeHash(window.location.hash || '');
    var initial = fromHash || fromServer;
    showPane(initial);

    shell.querySelectorAll('.admin-tab[data-tab-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            showPane(btn.getAttribute('data-tab-target') || 'haberler');
        });
    });

    window.addEventListener('hashchange', function () {
        var h = normalizeHash(window.location.hash || '');
        if (h) {
            showPane(h);
        }
    });
})();
