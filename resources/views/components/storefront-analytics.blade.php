        {{-- Yandex.Metrika counter --}}
        <script>
            (function (m, e, t, r, i, k, a) {
                m[i] = m[i] || function () { (m[i].a = m[i].a || []).push(arguments); };
                m[i].l = 1 * new Date();
                for (var j = 0; j < document.scripts.length; j++) { if (document.scripts[j].src === r) { return; } }
                k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r, a.parentNode.insertBefore(k, a);
            })(window, document, 'script', 'https://mc.yandex.ru/metrika/tag.js?id=108760077', 'ym');

            window.dataLayer = window.dataLayer || [];
            ym(108760077, 'init', {
                ssr: true,
                webvisor: true,
                clickmap: true,
                ecommerce: 'dataLayer',
                referrer: document.referrer,
                url: location.href,
                accurateTrackBounce: true,
                trackLinks: true,
            });
            let previousStorefrontUrl = location.href;
            document.addEventListener('inertia:navigate', function () {
                if (location.href === previousStorefrontUrl) return;
                ym(108760077, 'hit', location.href, { referer: previousStorefrontUrl });
                previousStorefrontUrl = location.href;
            });
        </script>
        <noscript>
            <div>
                <img src="https://mc.yandex.ru/watch/108760077" class="absolute left-[-9999px]" width="1" height="1" alt="">
            </div>
        </noscript>
        {{-- /Yandex.Metrika counter --}}
