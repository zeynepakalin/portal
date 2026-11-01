(function () {
    function shortDayName(isoDate, index) {
        if (index === 0) {
            return 'Bugün';
        }
        var days = ['Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'];
        var date = new Date(isoDate);
        if (isNaN(date.getTime())) {
            return '-';
        }
        return days[date.getDay()];
    }

    function weatherFromWmoCode(code) {
        var c = typeof code === 'number' ? code : 0;
        if (c === 0) {
            return { symbol: '☀️', label: 'Açık', theme: 'clear' };
        }
        if (c === 1) {
            return { symbol: '🌤️', label: 'Çoğunlukla açık', theme: 'clear' };
        }
        if (c === 2) {
            return { symbol: '⛅', label: 'Parçalı bulutlu', theme: 'cloudy' };
        }
        if (c === 3) {
            return { symbol: '☁️', label: 'Kapalı', theme: 'cloudy' };
        }
        if (c === 45 || c === 48) {
            return { symbol: '🌫️', label: 'Sisli', theme: 'fog' };
        }
        if (c >= 51 && c <= 57) {
            return { symbol: '🌦️', label: 'Çisenti', theme: 'rain' };
        }
        if (c >= 61 && c <= 67) {
            return { symbol: '🌧️', label: 'Yağmurlu', theme: 'rain' };
        }
        if (c >= 71 && c <= 77) {
            return { symbol: '❄️', label: 'Karlı', theme: 'snow' };
        }
        if (c >= 80 && c <= 82) {
            return { symbol: '🌧️', label: 'Sağanak yağış', theme: 'rain' };
        }
        if (c >= 85 && c <= 86) {
            return { symbol: '🌨️', label: 'Kar sağanağı', theme: 'snow' };
        }
        if (c >= 95 && c <= 99) {
            return { symbol: '⛈️', label: 'Gök gürültülü fırtına', theme: 'storm' };
        }
        return { symbol: '🌤️', label: 'Değişken', theme: 'cloudy' };
    }

    var card = document.getElementById('weather-card');
    var weatherStatus = document.getElementById('weather-status');
    var weatherDesc = document.getElementById('weather-desc');
    var weatherCondition = document.getElementById('weather-condition');
    var weatherSymbol = document.getElementById('weather-symbol');
    var weatherForecast = document.getElementById('weather-forecast');

    if (card && weatherStatus && weatherDesc && weatherCondition && weatherSymbol && weatherForecast) {
        var addressEl = card.querySelector('.weather-address');
        var defaultLat = card.getAttribute('data-lat') || '38.634';
        var defaultLon = card.getAttribute('data-lon') || '27.382';
        var defaultAddress = addressEl ? addressEl.textContent : '';
        var hasLoaded = false;

        var renderWeather = function (lat, lon, locationLabel, locationMeta) {
            var url = 'https://api.open-meteo.com/v1/forecast?latitude=' + encodeURIComponent(lat)
                + '&longitude=' + encodeURIComponent(lon)
                + '&current=temperature_2m,weather_code,wind_speed_10m'
                + '&daily=weather_code,temperature_2m_max,temperature_2m_min'
                + '&forecast_days=7&timezone=Europe%2FIstanbul';

            return fetch(url)
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (!data || !data.current) {
                        throw new Error('Hava durumu verisi boş geldi.');
                    }

                    var cur = data.current;
                    var info = weatherFromWmoCode(cur.weather_code);

                    card.classList.remove('weather-card--loading');
                    card.classList.add('weather-card--' + info.theme);

                    weatherSymbol.textContent = info.symbol;
                    weatherStatus.textContent = cur.temperature_2m + '°C';
                    weatherCondition.textContent = info.label;
                    weatherDesc.textContent = 'Rüzgar: ' + cur.wind_speed_10m + ' km/s' + (locationMeta ? ' • ' + locationMeta : '');

                    if (addressEl && locationLabel) {
                        addressEl.textContent = locationLabel;
                    }

                    weatherForecast.innerHTML = '';
                    if (data.daily && data.daily.time && data.daily.weather_code) {
                        for (var i = 0; i < data.daily.time.length; i++) {
                            var dayCode = data.daily.weather_code[i];
                            var dayInfo = weatherFromWmoCode(dayCode);
                            var day = document.createElement('div');
                            day.className = 'forecast-day';

                            var dayName = document.createElement('span');
                            dayName.className = 'forecast-name';
                            dayName.textContent = shortDayName(data.daily.time[i], i);

                            var dayIcon = document.createElement('span');
                            dayIcon.className = 'forecast-icon';
                            dayIcon.textContent = dayInfo.symbol;

                            var max = Math.round(data.daily.temperature_2m_max[i]);
                            var min = Math.round(data.daily.temperature_2m_min[i]);
                            var dayTemp = document.createElement('span');
                            dayTemp.className = 'forecast-temp';
                            dayTemp.textContent = max + '° / ' + min + '°';

                            day.appendChild(dayName);
                            day.appendChild(dayIcon);
                            day.appendChild(dayTemp);
                            weatherForecast.appendChild(day);
                        }
                    }
                });
        };

        var loadDefaultWeather = function (reasonText) {
            if (hasLoaded) {
                return;
            }
            hasLoaded = true;
            renderWeather(defaultLat, defaultLon, defaultAddress, reasonText || '')
                .catch(function () {
                    card.classList.remove('weather-card--loading');
                    card.classList.add('weather-card--cloudy');
                    weatherSymbol.textContent = '—';
                    weatherStatus.textContent = '--';
                    weatherCondition.textContent = 'Veri alınamadı';
                    weatherDesc.textContent = 'Hava durumu şu an alınamıyor.';
                    weatherForecast.innerHTML = '';
                });
        };

        if (addressEl) {
            addressEl.textContent = 'Konum alınıyor...';
        }
        weatherDesc.textContent = 'Konum izni bekleniyor...';

        if (!navigator.geolocation) {
            loadDefaultWeather('Sabit konum');
        } else {
            navigator.geolocation.getCurrentPosition(function (position) {
                if (hasLoaded) {
                    return;
                }
                hasLoaded = true;
                var lat = position.coords.latitude;
                var lon = position.coords.longitude;
                var locationMeta = lat.toFixed(3) + ', ' + lon.toFixed(3);
                renderWeather(lat, lon, 'Bulunduğun konum', locationMeta)
                    .catch(function () {
                        hasLoaded = false;
                        loadDefaultWeather('Sabit konum');
                    });
            }, function () {
                loadDefaultWeather('Sabit konum');
            }, {
                enableHighAccuracy: false,
                timeout: 8000,
                maximumAge: 300000
            });
        }
    }

    var weatherWidget = document.getElementById('weather-widget');
    if (weatherWidget) {
        var weatherCard = document.getElementById('weather-card');
        var noteEl = document.getElementById('market-note');
        var usdEl = document.getElementById('fx-usdtry');
        var eurEl = document.getElementById('fx-eurtry');
        var gbpEl = document.getElementById('fx-gbptry');
        var gramEl = document.getElementById('gold-gram');
        var quarterEl = document.getElementById('gold-quarter');
        var republicEl = document.getElementById('gold-republic');

        var isArtifactUsdTimes100Gram = function (gram, usdTry) {
            if (typeof gram !== 'number' || typeof usdTry !== 'number' || !isFinite(gram) || !isFinite(usdTry)) {
                return false;
            }
            var artifact = usdTry * 100;
            var tol = Math.max(25, artifact * 0.015);
            return Math.abs(gram - artifact) <= tol;
        };
        var widgetTabs = document.querySelectorAll('.widget-tab[data-widget-tab]');

        var setNumber = function (el, value, suffix, changeValue) {
            if (!el) {
                return;
            }
            if (typeof value !== 'number' || !isFinite(value)) {
                el.textContent = '--';
                el.classList.remove('market-up', 'market-down');
                return;
            }
            el.textContent = value.toFixed(2).replace('.', ',') + (suffix || '');
            el.classList.remove('market-up', 'market-down');
            if (typeof changeValue === 'number' && isFinite(changeValue)) {
                if (changeValue > 0) {
                    el.classList.add('market-up');
                } else if (changeValue < 0) {
                    el.classList.add('market-down');
                }
            }
        };

        var setNote = function (text) {
            if (noteEl) {
                noteEl.textContent = text;
            }
        };

        var isBusinessHours = function () {
            var now = new Date();
            var day = now.getDay(); // 0 pazar, 6 cumartesi
            if (day === 0 || day === 6) {
                return false;
            }
            var minuteOfDay = now.getHours() * 60 + now.getMinutes();
            return minuteOfDay >= (8 * 60 + 30) && minuteOfDay <= (18 * 60 + 30);
        };

        var switchWidgetPane = function (target) {
            var pane = weatherWidget.querySelector('[data-widget-pane="' + target + '"]');
            if (!pane) {
                return;
            }
            weatherWidget.scrollTo({ left: pane.offsetLeft, behavior: 'smooth' });
            widgetTabs.forEach(function (tab) {
                var on = tab.getAttribute('data-widget-tab') === target;
                tab.classList.toggle('is-active', on);
                tab.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        };

        var parseMarketPayload = function (body) {
            if (!body || body.ok !== true || !body.fx) {
                var errText = (body && body.message) ? body.message : 'Eksik piyasa verisi';
                throw new Error(errText);
            }

            var readNode = function (node) {
                if (!node || node.value === null || node.value === undefined) {
                    return NaN;
                }
                var n = Number(node.value);
                return typeof n === 'number' && isFinite(n) ? n : NaN;
            };
            var readChange = function (node) {
                if (!node || node.change === null || node.change === undefined) {
                    return NaN;
                }
                var n = Number(node.change);
                return typeof n === 'number' && isFinite(n) ? n : NaN;
            };

            var usd = body.fx.usdtry ? Number(body.fx.usdtry.value) : NaN;
            var eur = body.fx.eurtry ? Number(body.fx.eurtry.value) : NaN;
            var gbp = body.fx.gbptry ? Number(body.fx.gbptry.value) : NaN;
            var gram = body.gold ? readNode(body.gold.gram) : NaN;
            var quarter = body.gold ? readNode(body.gold.quarter) : NaN;
            var republic = body.gold ? readNode(body.gold.republic) : NaN;
            if (isFinite(usd) && isArtifactUsdTimes100Gram(gram, usd)) {
                gram = NaN;
                quarter = NaN;
                republic = NaN;
            }
            var usdCh = body.fx.usdtry ? Number(body.fx.usdtry.change) : NaN;
            var eurCh = body.fx.eurtry ? Number(body.fx.eurtry.change) : NaN;
            var gbpCh = body.fx.gbptry ? Number(body.fx.gbptry.change) : NaN;
            var gramCh = body.gold ? readChange(body.gold.gram) : NaN;
            var quarterCh = body.gold ? readChange(body.gold.quarter) : NaN;
            var republicCh = body.gold ? readChange(body.gold.republic) : NaN;

            setNumber(usdEl, usd, ' TL', usdCh);
            setNumber(eurEl, eur, ' TL', eurCh);
            setNumber(gbpEl, gbp, ' TL', gbpCh);
            setNumber(gramEl, gram, ' TL', gramCh);
            setNumber(quarterEl, quarter, ' TL', quarterCh);
            setNumber(republicEl, republic, ' TL', republicCh);
            var source = body.source ? String(body.source) : 'Portal servisi';
            setNote('Kaynak: ' + source + ' • 60 sn yenileme (mesai saatinde)');
            if (weatherCard) {
                weatherCard.classList.remove('weather-card--loading');
            }
        };

        var loadMarketDataFromLocalEndpoint = function () {
            return fetch('/portal/api/market.php?ts=' + Date.now(), { cache: 'no-store' })
                .then(function (res) {
                    return res.json().then(function (payload) {
                        return { status: res.status, body: payload };
                    });
                })
                .then(function (data) {
                    var body = data ? data.body : null;
                    parseMarketPayload(body);
                });
        };

        var loadMarketDataFromBrowserFallback = function () {
            return Promise.all([
                fetch('https://open.er-api.com/v6/latest/USD', { cache: 'no-store' }).then(function (res) {
                    return res.json();
                }),
                fetch('https://finans.truncgil.com/v4/today.json', { cache: 'no-store' })
                    .then(function (res) { return res.json(); })
                    .catch(function () { return null; })
            ]).then(function (results) {
                var fx = results[0];
                var tg = results[1];
                if (!fx || fx.result !== 'success' || !fx.rates || !fx.rates.TRY || !fx.rates.EUR || !fx.rates.GBP) {
                    throw new Error('Yedek doviz kaynagi gecersiz');
                }
                var usdTry = Number(fx.rates.TRY);
                var eurTry = usdTry / Number(fx.rates.EUR);
                var gbpTry = usdTry / Number(fx.rates.GBP);
                var gramTry = null;
                var quarterTry = null;
                var republicTry = null;
                var goldSrc = 'browser: altın yok (kur×100 yedek kaldırıldı)';
                if (tg && tg.GRA && typeof tg.GRA.Selling === 'number' && tg.GRA.Selling > 500) {
                    var g = Number(tg.GRA.Selling);
                    if (!isArtifactUsdTimes100Gram(g, usdTry)) {
                        gramTry = g;
                        quarterTry = tg.CEYREKALTIN && typeof tg.CEYREKALTIN.Selling === 'number'
                            ? Number(tg.CEYREKALTIN.Selling)
                            : gramTry * 1.754;
                        republicTry = tg.CUMHURIYETALTINI && typeof tg.CUMHURIYETALTINI.Selling === 'number'
                            ? Number(tg.CUMHURIYETALTINI.Selling)
                            : gramTry * 7.216;
                        goldSrc = 'browser: finans.truncgil.com';
                    }
                }
                parseMarketPayload({
                    ok: true,
                    fx: {
                        usdtry: { value: usdTry, change: null },
                        eurtry: { value: eurTry, change: null },
                        gbptry: { value: gbpTry, change: null }
                    },
                    gold: {
                        gram: { value: gramTry, change: null },
                        quarter: { value: quarterTry, change: null },
                        republic: { value: republicTry, change: null }
                    },
                    source: goldSrc
                });
            });
        };

        var loadMarketData = function () {
            loadMarketDataFromLocalEndpoint().catch(function () {
                loadMarketDataFromBrowserFallback().catch(function (err) {
                    var msg = err && err.message ? err.message : 'Piyasa verileri şu an alınamıyor.';
                    setNote(msg);
                    if (weatherCard) {
                        weatherCard.classList.remove('weather-card--loading');
                    }
                });
            });
        };

        widgetTabs.forEach(function (btn) {
            btn.addEventListener('click', function () {
                switchWidgetPane(btn.getAttribute('data-widget-tab') || 'weather');
            });
        });

        weatherWidget.addEventListener('scroll', function () {
            var index = Math.round(weatherWidget.scrollLeft / Math.max(1, weatherWidget.clientWidth));
            var target = index >= 1 ? 'market' : 'weather';
            widgetTabs.forEach(function (tab) {
                var on = tab.getAttribute('data-widget-tab') === target;
                tab.classList.toggle('is-active', on);
                tab.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        });

        weatherWidget.querySelectorAll('.market-tab[data-market-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = btn.getAttribute('data-market-tab') || 'fx';
                weatherWidget.querySelectorAll('.market-tab[data-market-tab]').forEach(function (tab) {
                    var on = tab === btn;
                    tab.classList.toggle('is-active', on);
                    tab.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                weatherWidget.querySelectorAll('.market-pane[data-market-pane]').forEach(function (pane) {
                    var on = pane.getAttribute('data-market-pane') === target;
                    if (on) {
                        pane.removeAttribute('hidden');
                    } else {
                        pane.setAttribute('hidden', 'hidden');
                    }
                });
            });
        });

        loadMarketData();
        setInterval(function () {
            if (isBusinessHours()) {
                loadMarketData();
            }
        }, 60000);
    }

    var carousels = document.querySelectorAll('[data-news-carousel]');
    carousels.forEach(function (carousel) {
        var track = carousel.querySelector('[data-news-track]');
        var prevBtn = carousel.querySelector('[data-carousel-prev]');
        var nextBtn = carousel.querySelector('[data-carousel-next]');
        var dotsWrap = carousel.querySelector('[data-carousel-dots]');
        if (!track || !prevBtn || !nextBtn || !dotsWrap) {
            return;
        }

        var slides = track.querySelectorAll('.news-slide');
        if (!slides.length) {
            return;
        }

        var currentIndex = 0;
        var autoTimer = null;
        var AUTO_MS = 5000;

        var slideWidth = function () {
            return carousel.clientWidth;
        };

        var goTo = function (index) {
            if (index < 0) {
                index = slides.length - 1;
            }
            if (index >= slides.length) {
                index = 0;
            }
            currentIndex = index;
            track.scrollTo({ left: slideWidth() * currentIndex, behavior: 'smooth' });
            updateDots();
        };

        var updateDots = function () {
            var dots = dotsWrap.querySelectorAll('.carousel-dot');
            dots.forEach(function (dot, idx) {
                dot.classList.toggle('active', idx === currentIndex);
            });
        };

        var startAuto = function () {
            if (autoTimer) {
                clearInterval(autoTimer);
            }
            autoTimer = setInterval(function () {
                goTo(currentIndex + 1);
            }, AUTO_MS);
        };

        var stopAuto = function () {
            if (autoTimer) {
                clearInterval(autoTimer);
                autoTimer = null;
            }
        };

        dotsWrap.innerHTML = '';
        slides.forEach(function (_, idx) {
            var dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'carousel-dot' + (idx === 0 ? ' active' : '');
            dot.setAttribute('aria-label', (idx + 1) + '. habere git');
            dot.addEventListener('click', function () {
                goTo(idx);
                startAuto();
            });
            dotsWrap.appendChild(dot);
        });

        prevBtn.addEventListener('click', function () {
            goTo(currentIndex - 1);
            startAuto();
        });

        nextBtn.addEventListener('click', function () {
            goTo(currentIndex + 1);
            startAuto();
        });

        carousel.addEventListener('mouseenter', stopAuto);
        carousel.addEventListener('mouseleave', startAuto);

        window.addEventListener('resize', function () {
            track.scrollTo({ left: slideWidth() * currentIndex, behavior: 'auto' });
        });

        startAuto();
    });

    document.addEventListener('click', function (event) {
        var arrow = event.target.closest('.menu-day-arrow');
        if (!arrow || arrow.classList.contains('is-disabled')) {
            return;
        }
        var panel = arrow.closest('.panel-menu');
        if (!panel || !arrow.href) {
            return;
        }

        event.preventDefault();
        if (panel.classList.contains('is-loading')) {
            return;
        }

        panel.classList.add('is-loading');
        fetch(arrow.href, { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Menu paneli alinamadi');
                }
                return response.text();
            })
            .then(function (html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var nextPanel = doc.querySelector('.panel-menu');
                if (!nextPanel) {
                    throw new Error('Yeni menu paneli bulunamadi');
                }
                panel.replaceWith(nextPanel);
                if (window.history && typeof window.history.pushState === 'function') {
                    window.history.pushState({}, '', arrow.href);
                }
            })
            .catch(function () {
                panel.classList.remove('is-loading');
            });
    });
})();
